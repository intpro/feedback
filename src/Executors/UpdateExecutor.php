<?php

namespace Interpro\Feedback\Executors;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Executor\AUpdateExecutor;
use Interpro\Core\Contracts\Mediator\RefConsistMediator;
use Interpro\Core\Contracts\Mediator\UpdateMediator;
use Interpro\Core\Contracts\Ref\ARef as ARefInterface;
use Interpro\Core\Taxonomy\Enum\TypeMode;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Feedback\Exception\FeedbackException;
use Interpro\Feedback\Model\Fbac;
use Interpro\Feedback\Model\Fbform;
use Interpro\Feedback\Model\Fbmail;
use Interpro\Feedback\Model\Fbmailto;

class UpdateExecutor implements AUpdateExecutor
{
    private $refConsistMediator;
    private $updateMediator;

    public function __construct(RefConsistMediator $refConsistMediator, UpdateMediator $updateMediator)
    {
        $this->updateMediator = $updateMediator;
        $this->refConsistMediator = $refConsistMediator;
    }

    /**
     * @return string
     */
    public function getFamily()
    {
        return 'feedback';
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     * @param array $values
     *
     * @return void
     */
    public function update(ARefInterface $Aref, array $values)
    {
        $type = $Aref->getType();
        $type_name = $type->getName();

        $id = $Aref->getId();

        $type_rank = $type->getRank();

        $self_owns = ['id','name','from','subject','to','username','email','body','mailed','host','port','encryption','report','password','domain'];

        //[[[
        DB::beginTransaction();

        if($type_rank === TypeRank::BLOCK)
        {
            $model = Fbform::where('name', '=', $type_name)->first();

            if($model)
            {
                $model->name = $type_name;

                foreach(['from','to','subject','username','password','host','port','encryption'] as $field_name)
                {
                    if(array_key_exists($field_name, $values))
                    {
                        $model->$field_name = (string) $values[$field_name];
                    }
                }

                $model->save();
            }
            else
            {
                throw new FeedbackException('Не найдена форма по имени '.$type_name.'!');
            }
        }
        elseif($type_rank === TypeRank::GROUP)
        {
            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);

                if($suffix === 'mail')
                {
                    $model = Fbmail::find($id);

                    if(!$model)
                    {
                        throw new FeedbackException('Не найдено письмо '.$type_name.'('.$id.')!');
                    }

                    foreach(['from','subject','to','username','email','body','host','port','encryption','report'] as $field_name)
                    {
                        if(array_key_exists($field_name, $values))
                        {
                            $model->$field_name = (string) $values[$field_name];
                        }
                    }

                    if(array_key_exists('mailed', $values))
                    {
                        if(is_string($values['mailed']) and $values['mailed'] = 'false')
                        {
                            $model->mailed = false;
                        }
                        else
                        {
                            $model->mailed = (bool) $values['mailed'];
                        }
                    }

                }
                elseif($suffix === 'mailto')
                {
                    $model = Fbmailto::find($id);

                    if(!$model)
                    {
                        throw new FeedbackException('Не найден отправитель '.$type_name.'('.$id.')!');
                    }

                    if(array_key_exists('to', $values))
                    {
                        $model->to = (string) $values['to'];
                    }
                }
                else
                {
                    throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
                }
            }
            elseif($type_name === 'mailfromac')
            {
                $model = Fbac::find($id);

                if(!$model)
                {
                    throw new FeedbackException('Не найден элемент автоподстановки '.$type_name.'('.$id.')!');
                }

                foreach(['domain','host','port','encryption'] as $field_name)
                {
                    if(array_key_exists($field_name, $values))
                    {
                        $model->$field_name = (string) $values[$field_name];
                    }
                    else
                    {
                        $model->$field_name = '';
                    }
                }
            }
            else
            {
                throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
            }

            $model->save();
        }
        else
        {
            throw new FeedbackException('При сохранении изменений передан тип с рангом отличным от блока и группы: '.$type->getRank().'!');
        }

        //Ссылки на внешние А типы потом дописать

        $owns = $type->getOwns();

        foreach($owns as $own_name => $own)
        {
            if(in_array($own_name, $self_owns))
            {
                continue;
            }

            $family = $own->getFieldTypeFamily();
            $mode = $own->getMode();

            if(array_key_exists($own_name, $values))
            {
                $value = $values[$own_name];

                if($mode === TypeMode::MODE_B)
                {
                    $updater = $this->updateMediator->getBUpdateExecutor($family);
                    $updater->update($Aref, $own, $value);
                }
                elseif($mode === TypeMode::MODE_C)
                {
                    $updater = $this->updateMediator->getCUpdateExecutor($family);
                    $updater->update($Aref, $own, $value);
                }
            }
        }

        DB::commit();
        //]]]
    }
}
