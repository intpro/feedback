<?php

namespace Interpro\Feedback;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Interpro\Core\Contracts\Mediator\DestructMediator;
use Interpro\Core\Contracts\Mediator\InitMediator;
use Interpro\Core\Contracts\Mediator\RefConsistMediator;
use Interpro\Core\Contracts\Mediator\SyncMediator;
use Interpro\Core\Contracts\Mediator\UpdateMediator;
use Interpro\Extractor\Contracts\Creation\CItemBuilder;
use Interpro\Extractor\Contracts\Creation\CollectionFactory;
use Interpro\Extractor\Contracts\Db\JoinMediator;
use Interpro\Extractor\Contracts\Db\MappersMediator;
use Interpro\Extractor\Contracts\Selection\Tuner;
use Interpro\Feedback\Creation\FeedbackItemFactory;
use Interpro\Feedback\Db\FeedbackAMapper;
use Interpro\Feedback\Db\FeedbackJoiner;
use Interpro\Feedback\Db\FeedbackQuerier;
use Interpro\Feedback\Executors\Destructor;
use Interpro\Feedback\Executors\Initializer;
use Interpro\Feedback\Executors\RefConsistExecutor;
use Interpro\Feedback\Executors\Synchronizer;
use Interpro\Feedback\Executors\UpdateExecutor;

class FeedbackSecondServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(Dispatcher $dispatcher,
                         CollectionFactory $collectionFactory,
                         MappersMediator $mappersMediator,
                         JoinMediator $joinMediator,
                         CItemBuilder $cItemBuilder,
                         InitMediator $initMediator,
                         SyncMediator $syncMediator,
                         UpdateMediator $updateMediator,
                         DestructMediator $destructMediator,
                         RefConsistMediator $refConsistMediator,
                         Tuner $tuner)
    {
        //Log::info('Загрузка FeedbackSecondServiceProvider');

        $querier = new FeedbackQuerier($joinMediator);

        $factory = new FeedbackItemFactory($collectionFactory);
        $mapper = new FeedbackAMapper($factory, $collectionFactory, $cItemBuilder, $mappersMediator, $querier, $tuner);

        $mappersMediator->registerAMapper($mapper);

        $joiner = new FeedbackJoiner($joinMediator);
        $joinMediator->registerJoiner($joiner);

        $initializer = new Initializer($refConsistMediator, $initMediator);
        $initMediator->registerAInitializer($initializer);

        $synchronizer = new Synchronizer($syncMediator, $initMediator);
        $syncMediator->registerASynchronizer($synchronizer);

        $updateExecutor = new UpdateExecutor($refConsistMediator, $updateMediator);
        $updateMediator->registerAUpdateExecutor($updateExecutor);

        $destructor = new Destructor($refConsistMediator, $destructMediator);
        $destructMediator->registerADestructor($destructor);

        $refConsistExecutor = new RefConsistExecutor();
        $refConsistMediator->registerRefConsistExecutor($refConsistExecutor);

    }

    /**
     * @return void
     */
    public function register()
    {
        //Log::info('Регистрация FeedbackSecondServiceProvider');

        $config = config('interpro.feedback');

        if($config)
        {
            $typeRegistrator = App::make('Interpro\Core\Contracts\Taxonomy\TypeRegistrator');
            $forecastList = App::make('Interpro\Core\Contracts\Taxonomy\TypesForecastList');

            $configInterpreter = new FeedbackConfigInterpreter($forecastList);

            $manifests = $configInterpreter->interpretConfig($config);

            foreach($manifests as $manifest)
            {
                $typeRegistrator->registerType($manifest);
            }
        }
    }

}
