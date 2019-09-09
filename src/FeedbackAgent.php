<?php

namespace Interpro\Feedback;

use Illuminate\Support\Facades\View;
use Interpro\Core\Taxonomy\Enum\TypeMode;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Entrance\Contracts\CommandAgent\InitAgent;
use Interpro\Entrance\Contracts\CommandAgent\UpdateAgent;
use Interpro\Entrance\Contracts\Extract\ExtractAgent;
use Interpro\Extractor\Contracts\Items\AItem;
use Interpro\Feedback\Contracts\FeedbackAgent as FeedbackAgentInterface;
use Interpro\Feedback\Exception\FeedbackException;

class FeedbackAgent implements FeedbackAgentInterface
{
    private $body_templates = [];
    private $extractAgent;
    private $initAgent;
    private $updateAgent;

    public function __construct(ExtractAgent $extractAgent, InitAgent $initAgent, UpdateAgent $updateAgent)
    {
        $this->extractAgent = $extractAgent;
        $this->initAgent = $initAgent;
        $this->updateAgent = $updateAgent;
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

    private function getMailConfig(AItem $feedback, AItem $form)
    {
        $conf_params = ['from'=>'', 'to'=>'', 'subject'=>'', 'username'=>'', 'password'=>'', 'host'=>'', 'port'=>'', 'encryption'=>''];

        foreach($conf_params as $field_name => $field_value)
        {
            //Попытка 1 - получить из формы
            $form_field_value = $form->$field_name;

            //Попытка 2 - получить из общих настроек
            if(!$form_field_value)
            {
                $form_field_value = $feedback->$field_name;
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
     * @return \Interpro\Extractor\Contracts\Items\AItem
     */
    public function mail($form, array $fields = [])
    {
        $feedbackBlock = $this->extractAgent->getBlock('feedback');
        $formBlock = $this->extractAgent->getBlock($form);

        $group_name = $form.'_mail';

        $config = $this->getMailConfig($feedbackBlock, $formBlock);

        $template = $this->getBodyTemplate($form);

        $mailItem = $this->initAgent->init($group_name, array_merge($fields, $config));

        $id = $mailItem->id;

        $params = [];

        foreach($mailItem->getOwns() as $own)
        {
            if($own->getFieldMeta()->getMode() === TypeMode::MODE_C)
            {
                $params[$own->getName()] = $own->getItem()->getValue();
            }
        }

        $body = View::make($template, $params)->render();
        $update_fields['body'] = $body;

        $backup = \Illuminate\Support\Facades\Mail::getSwiftMailer();

        $transport = new \Swift_SmtpTransport($config['host'], $config['port'], $config['encryption']);
        $transport->setUsername($config['username']);
        $transport->setPassword($config['password']);

        $tr_mail = new \Swift_Mailer($transport);

        \Illuminate\Support\Facades\Mail::setSwiftMailer($tr_mail);

        $updateAgent = $this->updateAgent;

        $copies = [];
        foreach($formBlock->getGroup($form.'_mailto') as $mailto)
        {
            $copies[] = $mailto->to;
        }

        try{
            \Illuminate\Support\Facades\Mail::send($template, $params,
                function($message) use ($config, $group_name, $id, $copies, $updateAgent)
                {
                    $message->from($config['from']);
                    $message->to($config['to']);
                    $message->subject($config['subject']);

                    foreach($copies as $copy)
                    {
                        $message->cc($copy);
                    }

                    $updateAgent->update($group_name, $id, ['mailed' => true]);
                });

            \Illuminate\Support\Facades\Mail::setSwiftMailer($backup);

        }catch (\Exception $exception){

            $update_fields['mailed'] = false;
            $update_fields['report'] = $exception->getMessage();
        }

        $update_fields['body'] = $body;

        $this->updateAgent->update($group_name, $mailItem->id, $update_fields);

        $mailItem = $this->extractAgent->getGroupItem($group_name, $mailItem->id);

        return $mailItem;
    }
}
