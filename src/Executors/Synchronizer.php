<?php

namespace Interpro\Feedback\Executors;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Executor\ASynchronizer as ASynchronizerInterface;
use Interpro\Core\Contracts\Mediator\InitMediator;
use Interpro\Core\Contracts\Mediator\SyncMediator;
use Interpro\Core\Contracts\Taxonomy\Types\AType;
use Interpro\Core\Ref\ARef;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Feedback\Exception\FeedbackException;
use Interpro\Feedback\Model\Fbac;
use Interpro\Feedback\Model\Fbform;
use Interpro\Feedback\Model\Fbmail;
use Interpro\Feedback\Model\Fbmailto;

class Synchronizer implements ASynchronizerInterface
{
    private $initMediator;
    private $syncMediator;

    public function __construct(SyncMediator $syncMediator, InitMediator $initMediator)
    {
        $this->initMediator = $initMediator;
        $this->syncMediator = $syncMediator;
    }

    /**
     * @return string
     */
    public function getFamily()
    {
        return 'feedback';
    }

    private function syncEntity(AType $type, $id)
    {
        $aRef = new ARef($type, $id);

        $self_owns = ['id','name','from','subject','to','username','email','body','mailed','host','port','encryption','report','password','domain'];

        $owns = $type->getOwns();

        foreach($owns as $own)
        {
            $name = $own->getName();

            if(in_array($name, $self_owns))
            {
                continue;
            }

            $family = $own->getFieldTypeFamily();

            $synchronizer = $this->syncMediator->getOwnSynchronizer($family);

            $synchronizer->sync($aRef, $own);
        }
    }

    /**
     * @param \Interpro\Core\Contracts\Taxonomy\Types\AType $type
     *
     * @return \Interpro\Core\Contracts\Ref\ARef
     */
    public function sync(AType $type)
    {
        $type_name = $type->getName();

        //Не предопределенных ссылок пока нет

        //[[[
        DB::beginTransaction();

        if($type->getRank() === TypeRank::BLOCK)
        {
            $model = Fbform::where('name', '=', $type_name)->first();
            if(!$model)
            {
                $initializer = $this->initMediator->getAInitializer($type->getFamily());
                $blockRef = $initializer->init($type);
            }
            else
            {
                $this->syncEntity($type, 0);
                //$this->syncRefs($type, $item->id);
            }
        }
        elseif($type->getRank() === TypeRank::GROUP)
        {

            $suffix_pos = strripos($type_name, '_');

            if($suffix_pos)
            {
                $suffix = substr($type_name, $suffix_pos+1);

                if($suffix === 'mail')
                {
                    $collection = Fbmail::where('name', '=', $type_name)->get();
                }
                elseif($suffix === 'mailto')
                {
                    $collection = Fbmailto::where('name', '=', $type_name)->get();
                }
                else
                {
                    throw new FeedbackException('Синхронизатор feedback не обрабатывает тип '.$type_name.'!');
                }
            }
            elseif($type_name === 'mailfromac')
            {
                $collection = Fbac::all();
            }
            else
            {
                throw new FeedbackException('Синхронизатор feedback не обрабатывает тип '.$type_name.'!');
            }

            foreach($collection as $item)
            {
                $this->syncEntity($type, $item->id);
                //$this->syncRefs($type, $item->id);
            }
        }

        DB::commit();
        //]]]
    }
}
