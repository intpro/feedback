<?php

namespace Interpro\Feedback;

use Interpro\Entrance\Contracts\CommandAgent\InitAgent;
use Interpro\Entrance\Contracts\Extract\ExtractAgent;
use Interpro\Feedback\Contracts\FeedbackAgent as FeedbackAgentInterface;
use Interpro\Feedback\Exception\FeedbackException;

class FeedbackAgent implements FeedbackAgentInterface
{
    private $body_templates = [];
    private $extractAgent;
    private $initAgent;

    public function __construct(ExtractAgent $extractAgent, InitAgent $initAgent)
    {
        $this->extractAgent = $extractAgent;
        $this->initAgent = $initAgent;
    }

    /**
     * @return string
     */
    private function getBodyTemplate($form)
    {
        if(array_key_exists($form, $this->body_templates))
        {
            return $this->body_templates[$form];
        }
        else
        {
            throw new FeedbackException('Не найден шаблон формы обратной связи '.$form.'!');
        }
    }

    /**
     * @param string $form
     * @param string $template
     * @return void
     */
    public function setBodyTemplate($form, $template)
    {
        $this->body_templates[$form] = $template;
    }

    private function getMailConfig($form)
    {
        $feedbackFields = $this->extractAgent->getBlock('feedback')->getOwns();
        $formFields = $this->extractAgent->getBlock($form)->getOwns();

        $conf_params = ['from'=>'', 'to'=>'', 'subject'=>'', 'username'=>'', 'password'=>'', 'host'=>'', 'port'=>'', 'encryption'=>''];

        foreach($conf_params as $field_name => $field_value)
        {
            //Попытка 1 - получить из формы
            if($formFields->exist($field_name))
            {
                $form_field_value = $formFields->getOwnByName($field_name);
            }
            else
            {
                $form_field_value = '';
            }

            //Попытка 2 - получить из общих настроек
            if(!$form_field_value)
            {
                if($feedbackFields->exist($field_name))
                {
                    $form_field_value = $feedbackFields->getOwnByName($field_name);
                }
            }

            //Попытка 3 - получить из конфига
            if(!$form_field_value)
            {
                $form_field_value = env(strtoupper('mail_'.$field_name), '');
            }

            $conf_params[$field_name] = $form_field_value;
        }

        return $conf_params;
    }

    /**
     * @param string $form
     * @param array $fields
     * @return void
     */
    public function mail($form, array $fields)
    {
        //Написано без очередей
        $config = $this->getMailConfig($form);

        $template = $this->getBodyTemplate($form);

        $backup = \Illuminate\Support\Facades\Mail::getSwiftMailer();

        $transport = \Swift_SmtpTransport::newInstance($config['host'], $config['port'], $config['encryption']);
        $transport->setUsername($config['username']);
        $transport->setPassword($config['password']);

        $tr_mail = new \Swift_Mailer($transport);

        \Illuminate\Support\Facades\Mail::setSwiftMailer($tr_mail);

        try{
            \Illuminate\Support\Facades\Mail::send($template, $fields,
                function($message) use ($config)
                {
                    $message->from($config['from']);
                    $message->to($config['to']);
                    $message->subject($config['subject']);
                });

            \Illuminate\Support\Facades\Mail::setSwiftMailer($backup);

            $fields['mailed'] = true;

            $this->initAgent->init($form.'_mail', array_merge($fields, $config));

        }catch (\Exception $exception){

            $fields['mailed'] = false;
            $fields['report'] = $exception->getMessage();

            $this->initAgent->init($form.'_mail', array_merge($fields, $config));
        }
    }
}
