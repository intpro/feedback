<?php

namespace Interpro\Feedback\Executors;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Executor\ADestructor;
use Interpro\Core\Contracts\Mediator\DestructMediator;
use Interpro\Core\Contracts\Mediator\RefConsistMediator;
use Interpro\Core\Contracts\Ref\ARef;
use Interpro\Core\Taxonomy\Enum\TypeMode;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Feedback\Exception\FeedbackException;
use Interpro\Feedback\Model\Fbac;
use Interpro\Feedback\Model\Fbform;
use Interpro\Feedback\Model\Fbmail;
use Interpro\Feedback\Model\Fbmailto;

class Destructor implements ADestructor
{
    private $refConsistMediator;
    private $destructMediator;
    private $selffields = ['id','name','from','subject','to','username','email','body','mailed','host','port','encryption','report','password','domain'];

    public function __construct(RefConsistMediator $refConsistMediator, DestructMediator $destructMediator)
    {
        $this->refConsistMediator = $refConsistMediator;
        $this->destructMediator = $destructMediator;
    }

    /**
     * @return string
     */
    public function getFamily()
    {
        return 'feedback';
    }

    private function deleteOwns(ARef $ref)
    {
        $type = $ref->getType();

        //Внешние поля
        $families = [];
        $owns = $type->getOwns();

        foreach($owns as $ownField)
        {
            if(in_array($ownField->getName(), $this->selffields))
            {
                continue;
            }

            $own_f_f = $ownField->getFieldTypeFamily();

            if(in_array($own_f_f, $families))
            {
                continue;
            }

            $families[] = $own_f_f;

            if($ownField->getMode() === TypeMode::MODE_B)
            {
                $destructor = $this->destructMediator->getBDestructor($own_f_f);
                $destructor->delete($ref);
            }
            elseif($ownField->getMode() === TypeMode::MODE_C)
            {
                $destructor = $this->destructMediator->getCDestructor($own_f_f);
                $destructor->delete($ref);
            }
        }
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     *
     * @return void
     */
    public function delete(ARef $ref)
    {
        $type = $ref->getType();
        $type_name = $type->getName();

        $id = $ref->getId();

        $type_rank = $type->getRank();

        //[[[
        DB::beginTransaction();

        //Удаление внешних собственных полей
        $this->deleteOwns($ref);

        //Сообщение ссылающимся, об удалении сущности
        //При удаленнии блока формы или общего блока feedback, подчиненные удаляются через разруливание ссылок Interpro\Feedback\Executors\RefConsistExecutor
        $this->refConsistMediator->notify($ref);

        if($type_rank === TypeRank::BLOCK)
        {
            Fbform::where('name', '=', $type_name)->delete();
        }
        elseif($type_rank === TypeRank::GROUP)
        {
            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);

                if($suffix === 'mail')
                {
                    Fbmail::where('name', '=', $type_name)->where('id', '=', $id)->delete();
                }
                elseif($suffix === 'mailto')
                {
                    Fbmailto::where('name', '=', $type_name)->where('id', '=', $id)->delete();
                }
            }
            elseif($type_name === 'mailfromac')
            {
                Fbac::where('id', '=', $id)->delete();
            }
        }
        else
        {
            throw new FeedbackException('В деструкторе qs возможно удаление только сущностей ранга блок или группа, передано: '.$type->getName().'('.$type_rank.').');
        }

        DB::commit();
        //]]]
    }
}
