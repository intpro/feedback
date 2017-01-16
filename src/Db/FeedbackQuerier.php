<?php

namespace Interpro\Feedback\Db;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Ref\ARef;
use Interpro\Core\Contracts\Taxonomy\Types\AType;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Extractor\Contracts\Db\JoinMediator;
use Interpro\Extractor\Contracts\Selection\SelectionUnit;
use Interpro\Extractor\Db\QueryBuilder;
use Interpro\Feedback\Exception\FeedbackException;

class FeedbackQuerier
{
    private $joinMediator;

    public function __construct(JoinMediator $joinMediator)
    {
        $this->joinMediator = $joinMediator;
    }

    private function getTable(AType $type)
    {
        $type_name = $type->getName();

        if($type->getRank() === TypeRank::BLOCK)
        {
            $table = 'fbforms';
        }
        elseif($type->getRank() === TypeRank::GROUP)
        {
            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);

                if($suffix === 'mail')
                {
                    $table = 'fbmails';
                }
                elseif($suffix === 'mailto')
                {
                    $table = 'fbmailtos';
                }
                else
                {
                    throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
                }
            }
            elseif($type_name === 'mailfromac')
            {
                $table = 'fbacs';
            }
            else
            {
                throw new FeedbackException('Имя типа '.$type_name.' не поддерживается!');
            }
        }
        else
        {
            throw new FeedbackException('При получении данных элемента передана ссылка на тип с рангом отличным от блока и группы: '.$type->getRank().'!');
        }

        return $table;
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     *
     * @return \Interpro\Extractor\Db\QueryBuilder
     */
    public function selectByRef(ARef $ref)
    {
        $type  = $ref->getType();
        $type_name = $type->getName();
        $id = $ref->getId();

        $table = $this->getTable($type);

        $query = new QueryBuilder(DB::table($table));
        $query->where($table.'.name', '=', $type_name);

        if($id > 0)
        {
            $query->where($table.'.id', '=', $id);
        }

        return $query;
    }

    /**
     * @param SelectionUnit $selectionUnit
     *
     * @return \Interpro\Extractor\Db\QueryBuilder
     * @throws \Interpro\QS\Exception\QSException
     */
    public function selectByUnit(SelectionUnit $selectionUnit)
    {
        $type  = $selectionUnit->getType();

        $entity_name    = $type->getName();

        $model_table = $this->getTable($type);

        $self_fields = ['id,from,subject,to,username,email,body,mailed,host,port,encryption,report,password,domain'];

        //Группировка путей с общими отрезками
        //-------------------------------------------------------------
        $join_fields = $selectionUnit->getJoinFieldsPaths();

        $join_array = ['sub_levels' => [], 'full_field_names' => [], 'value_level' => false];//первый уровень - соединение с главным запросом + пути к полям по уровням

        foreach($join_fields as $field)
        {
            if (in_array($field, $self_fields))
            {
                continue;
            }

            $curr_level_array = & $join_array;

            $field_array     = explode('.', $field);
            $full_field_name = str_replace('.', '_', $field);

            $curr_level_array['full_field_names'][] = $full_field_name;

            foreach($field_array as $field_name)
            {
                if(!array_key_exists($field_name, $curr_level_array['sub_levels']))
                {
                    $curr_level_array['sub_levels'][$field_name] = ['sub_levels' => [], 'full_field_names' => [], 'value_level' => false];
                }

                $curr_level_array = &$curr_level_array['sub_levels'][$field_name];

                $curr_level_array['full_field_names'][] = $full_field_name;
            }

            $curr_level_array['value_level'] = true;//В конце всегда должно стоять поле скалярного типа
        }
        //-------------------------------------------------------------

        //В главном запросе можно пользоваться биндингом, а в подзапросах нельзя, так как порядок параметров будет сбиваться параметрами подзапросов
        $main_query = new QueryBuilder(DB::table($model_table));
        $main_query->where($model_table.'.name', '=', $entity_name);

        $get_fields = [
            $model_table.'.name'
        ];

        if($model_table === 'fbforms')
        {
            $get_fields[] = $model_table.'.from';
            $get_fields[] = $model_table.'.to';
            $get_fields[] = $model_table.'.subject';
            $get_fields[] = $model_table.'.username';
            $get_fields[] = $model_table.'.password';
            $get_fields[] = $model_table.'.host';
            $get_fields[] = $model_table.'.port';
            $get_fields[] = $model_table.'.encryption';
        }
        elseif($model_table === 'fbmails')
        {
            $get_fields[] = $model_table.'.id';
            $get_fields[] = $model_table.'.form_name';
            $get_fields[] = $model_table.'.from';
            $get_fields[] = $model_table.'.subject';
            $get_fields[] = $model_table.'.to';
            $get_fields[] = $model_table.'.username';
            $get_fields[] = $model_table.'.email';
            $get_fields[] = $model_table.'.body';
            $get_fields[] = $model_table.'.mailed';
            $get_fields[] = $model_table.'.host';
            $get_fields[] = $model_table.'.port';
            $get_fields[] = $model_table.'.encryption';
            $get_fields[] = $model_table.'.report';
        }
        elseif($model_table === 'fbmailtos')
        {
            $get_fields[] = $model_table.'.id';
            $get_fields[] = $model_table.'.form_name';
            $get_fields[] = $model_table.'.to';
        }
        elseif($model_table === 'fbacs')
        {
            $get_fields[] = $model_table.'.id';
            $get_fields[] = $model_table.'.domain';
            $get_fields[] = $model_table.'.host';
            $get_fields[] = $model_table.'.port';
            $get_fields[] = $model_table.'.encryption';
        }

        //Сначала подсоединяем все, кроме slug, title, sorter, show
        //$field - путь к полю разделенный точками
        foreach($join_array['sub_levels'] as $level0_field_name => $sub_array)
        {
            $Field = $type->getField($level0_field_name);
            $join_q = $this->joinMediator->externalJoin($Field, $sub_array);

            $main_query->leftJoin(DB::raw('('.$join_q->toSql().') AS '.$level0_field_name.'_table'), function($join) use ($level0_field_name, $model_table)
            {
                $join->on($model_table.'.name', '=', $level0_field_name.'_table.entity_name');
                $join->on($model_table.'.id',   '=', $level0_field_name.'_table.entity_id');
            });

            //$main_query->addBinding($join_q->getBindings());

            $get_fields = array_merge($get_fields, $sub_array['full_field_names']);
        }

        //Применим все параметры условия и сортировки выборки к запросу:
        $selectionUnit->apply($main_query);

        $main_query->select($get_fields);

        return $main_query;
    }


}
