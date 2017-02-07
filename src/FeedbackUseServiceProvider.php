<?php

namespace Interpro\Feedback;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class FeedbackUseServiceProvider extends ServiceProvider {

    /**
     * @return void
     */
    public function boot(Dispatcher $dispatcher)
    {
        //Log::info('Загрузка FeedbackUseServiceProvider');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //Log::info('Регистрация FeedbackUseServiceProvider');

        $this->app->singleton(
            'Interpro\Feedback\Contracts\FeedbackAgent',
            'Interpro\Feedback\FeedbackAgent'
        );
    }

}
