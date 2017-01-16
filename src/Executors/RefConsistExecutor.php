<?php

namespace Interpro\Feedback\Executors;

use Interpro\Core\Contracts\Executor\RefConsistExecutor as RefConsistExecutorInterface;
use Interpro\Core\Contracts\Ref\ARef;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Feedback\Exception\FeedbackException;
use Interpro\Feedback\Model\Fbac;
use Interpro\Feedback\Model\Fbform;
use Interpro\Feedback\Model\Fbmail;
use Interpro\Feedback\Model\Fbmailto;

class RefConsistExecutor implements RefConsistExecutorInterface
{
    /**
     * @return string
     */
    public function getFamily()
    {
        return 'feedback';
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     *
     * @return void
     */
    public function execute(ARef $ref)
    {
        $type      = $ref->getType();
        $type_name = $type->getName();

        if($type->getFamily() === $this->getFamily())
        {
            if($type->getRank() === TypeRank::BLOCK)
            {
                if($type_name === 'feedback')
                {
                    //Fbac - очищать?
                }
                else//Если удаляется одна из форм, очистить письма и получателей
                {
                    Fbmail::where('form_name', '=', $type_name)->delete();
                    Fbmailto::where('form_name', '=', $type_name)->delete();
                }
            }
            elseif($type->getRank() === TypeRank::GROUP)
            {
                //Пока нет групп подчиненных группам и перекрестных ссылок в пакете
            }
        }
        else
        {
            //Когда будут ссылки на сторонние А записи - наполнить логику здесь
        }
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     *
     * @return bool
     */
    public function exist(ARef $ref)
    {
        $type = $ref->getType();
        $type_name = $type->getName();

        $id = $ref->getId();

        $type_rank = $type->getRank();

        if($type_rank === TypeRank::BLOCK)
        {
            $collection = Fbform::where('name', $type_name)->get();
        }
        elseif($type_rank === TypeRank::GROUP)
        {
            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);

                if($suffix === 'mail')
                {
                    $collection = Fbmail::where('name', '=', $type_name)->where('id', '=', $id)->get();
                }
                elseif($suffix === 'mailto')
                {
                    $collection = Fbmailto::where('name', '=', $type_name)->where('id', '=', $id)->get();
                }
                else
                {
                    throw new FeedbackException('Не корректный суффикс '.$type_name.' ссылки!');
                }
            }
            elseif($type_name === 'mailfromac')
            {
                $collection = Fbac::where('id', '=', $id)->get();
            }
            else
            {
                throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
            }
        }
        else
        {
            throw new FeedbackException('Не корректный ранг типа '.$type_name.' ссылки!');
        }

        return !$collection->isEmpty();
    }
}
