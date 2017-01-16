<?php

namespace Interpro\Feedback\Executors;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Executor\AInitializer;
use Interpro\Core\Contracts\Mediator\InitMediator;
use Interpro\Core\Contracts\Mediator\RefConsistMediator;
use Interpro\Core\Contracts\Taxonomy\Types\AType;
use Interpro\Core\Ref\ARef;
use Interpro\Core\Taxonomy\Enum\TypeMode;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Feedback\Exception\FeedbackException;
use Interpro\Feedback\Model\Fbac;
use Interpro\Feedback\Model\Fbform;
use Interpro\Feedback\Model\Fbmail;
use Interpro\Feedback\Model\Fbmailto;

class Initializer implements AInitializer
{
    private $refConsistMediator;
    private $initMediator;

    public function __construct(RefConsistMediator $refConsistMediator, InitMediator $initMediator)
    {
        $this->initMediator = $initMediator;
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
     * @param \Interpro\Core\Contracts\Taxonomy\Types\AType $type
     * @param array $defaults
     *
     * @return \Interpro\Core\Contracts\Ref\ARef
     */
    public function init(AType $type, array $defaults = [])
    {
        $type_name = $type->getName();

        $self_fields = ['id','name','from','subject','to','username','email','body','mailed','host','port','encryption','report','password','domain'];

        //[[[
        DB::beginTransaction();

        if($type->getRank() === TypeRank::BLOCK)
        {
            $model = Fbform::where('name', '=', $type_name)->first();

            if(!$model)
            {
                $model = new Fbform();
                $model->name = $type_name;

                foreach(['from','to','subject','username','password','host','port','encryption'] as $field_name)
                {
                    if(array_key_exists($field_name, $defaults))
                    {
                        $model->$field_name = (string) $defaults[$field_name];
                    }
                    else
                    {
                        $model->$field_name = '';
                    }
                }

                $model->save();
            }

            $id = 0;
        }
        elseif($type->getRank() === TypeRank::GROUP)
        {
            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);
                $name = substr($type_name, 0, $suffix_pos);

                if($suffix === 'mail')
                {
                    $model = new Fbmail();
                    $model->name = $type_name;
                    $model->form_name = $name;

                    foreach(['from','subject','to','username','email','body','host','port','encryption','report'] as $field_name)
                    {
                        if(array_key_exists($field_name, $defaults))
                        {
                            $model->$field_name = (string) $defaults[$field_name];
                        }
                        else
                        {
                            $model->$field_name = '';
                        }
                    }

                    $model->mailed = false;
                }
                elseif($suffix === 'mailto')
                {
                    $model = new Fbmailto();

                    $model->name = $type_name;
                    $model->form_name = $name;

                    if(array_key_exists('to', $defaults))
                    {
                        $model->to = (string) $defaults['to'];
                    }
                    else
                    {
                        $model->to = true;
                    }
                }
                else
                {
                    throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
                }
            }
            elseif($type_name === 'mailfromac')
            {
                $model = new Fbac();
                $model->name = $type_name;

                foreach(['domain','host','port','encryption'] as $field_name)
                {
                    if(array_key_exists($field_name, $defaults))
                    {
                        $model->$field_name = (string) $defaults[$field_name];
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

            $id = $model->id;
        }
        else
        {
            throw new FeedbackException('При инициализации передан тип с рангом отличным от блока и группы: '.$type->getRank().'!');
        }

        //Ссылки на внешние А типы потом дописать

        $Aref = new ARef($type, $id);
        //----------------------------------------------------------------------
        $owns = $type->getOwns();

        foreach($owns as $own_name => $own)
        {
            if(in_array($own_name, $self_fields))
            {
                continue;
            }

            $family = $own->getFieldTypeFamily();
            $mode = $own->getMode();

            if(array_key_exists($own_name, $defaults))
            {
                $value = $defaults[$own_name];
            }
            else
            {
                $value = null;
            }

            if($mode === TypeMode::MODE_B)
            {
                $initializer = $this->initMediator->getBInitializer($family);
                $initializer->init($Aref, $own, $value);
            }
            elseif($mode === TypeMode::MODE_C)
            {
                $initializer = $this->initMediator->getCInitializer($family);
                $initializer->init($Aref, $own, $value);
            }
        }

        DB::commit();
        //]]]

        return $Aref;
    }
}
