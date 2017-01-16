<?php

namespace Interpro\Feedback;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class FeedbackFirstServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(Dispatcher $dispatcher)
    {
        Log::info('Загрузка FeedbackFirstServiceProvider');

        $this->publishes([__DIR__.'/config/feedback.php' => config_path('interpro/feedback.php')]);
        $this->publishes([__DIR__.'/views/mailwrapper.blade.php' => resource_path('views/interpro/feedback/mailwrapper.blade.php')]);

        $this->publishes([
            __DIR__.'/migrations' => database_path('migrations')
        ], 'migrations');
    }

    /**
     * @return void
     */
    public function register()
    {
        Log::info('Регистрация FeedbackFirstServiceProvider');

        //Регистрируем имена, для интерпретации типов при загрузке
        $forecastList = $this->app->make('Interpro\Core\Contracts\Taxonomy\TypesForecastList');

        $forecastList->registerATypeName('feedback');
        $forecastList->registerATypeName('mailfromac');

        $forms = config('interpro.feedback.forms', []);
        if($forms)
        {
            foreach($forms as $form_name => $attr)
            {
                $forecastList->registerATypeName($form_name);
                $forecastList->registerATypeName($form_name.'_mail');
                $forecastList->registerATypeName($form_name.'_mailto');
            }
        }
    }

}
