<?php

namespace Interpro\Feedback;

use Interpro\Core\Contracts\Taxonomy\TypesForecastList;
use Interpro\Core\Taxonomy\Collections\ManifestsCollection;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Core\Taxonomy\Manifests\ATypeManifest;
use Interpro\Feedback\Exception\FeedbackException;

class FeedbackConfigInterpreter
{
    private $forecastList;

    public function __construct(TypesForecastList $forecastList)
    {
        $this->forecastList = $forecastList;
    }

    private function checkNames()
    {
        //Проверка на наличие обязательных скалярных типов для предопределённых полей
        $c_names = $this->forecastList->getCTypeNames();

        if(!in_array('string', $c_names))
        {
            throw new FeedbackException('Не зарегестрировано имя типа string, интерпретация предопределенных полей не возможна!');
        }

        if(!in_array('text', $c_names))
        {
            throw new FeedbackException('Не зарегестрировано имя типа text, интерпретация предопределенных полей не возможна!');
        }

        if(!in_array('int', $c_names))
        {
            throw new FeedbackException('Не зарегестрировано имя типа int, интерпретация предопределенных полей не возможна!');
        }

        if(!in_array('bool', $c_names))
        {
            throw new FeedbackException('Не зарегестрировано имя типа bool, интерпретация предопределенных полей не возможна!');
        }
    }

    /**
     * @param array $config
     *
     * @return \Interpro\Core\Taxonomy\Collections\ManifestsCollection
     */
    public function interpretConfig(array $config)
    {
        $manifests = new ManifestsCollection();

        $this->checkNames();

        $c_names = $this->forecastList->getCTypeNames();
        $b_names = $this->forecastList->getBTypeNames();
        $a_names = $this->forecastList->getATypeNames();

        $domain_owns = [
            'host' => 'string',
            'port' => 'string',
            'encryption' => 'string'
        ];

        //name, from, to, subject, password, host, port, encryption

        //Интерпретируем конфиг настроек по умолчанию
        $feedback_owns = array_merge(['name' => 'string', 'from' => 'string', 'to' => 'string', 'subject' => 'string','username' => 'string','password' => 'string'], $domain_owns);
        $feedback_refs = [];

        if(array_key_exists('feedback', $config))
        {
            foreach($config['feedback'] as $feedback_key => $feedback_value)
            {
                if(is_string($feedback_key))
                {
                    if(in_array($feedback_key, $c_names))
                    {
                        foreach($feedback_value as $own_name)
                        {
                            $feedback_owns[$own_name] = $feedback_key;
                        }
                    }
                    elseif(in_array($feedback_key, $b_names))
                    {
                        foreach($feedback_value as $own_name)
                        {
                            $feedback_owns[$own_name] = $feedback_key;
                        }
                    }
                    elseif(in_array($feedback_key, $a_names))
                    {
                        foreach($feedback_value as $ref_name)
                        {
                            $feedback_refs[$ref_name] = $feedback_key;
                        }
                    }
                }
            }
        }

        $feedbackMan = new ATypeManifest('feedback', 'feedback', TypeRank::BLOCK, $feedback_owns, $feedback_refs);

        $manifests->addManifest($feedbackMan);


        //Добавляем группу автоподстановки
        $feedbackAC = new ATypeManifest(
            'feedback',
            'mailfromac',
            TypeRank::GROUP,
            array_merge([
                'id' => 'int',
                'name' => 'string',
                'domain' => 'string'
            ], $domain_owns),
            ['block_name' => 'feedback']);

        $manifests->addManifest($feedbackAC);


        //Добавляем блоки форм
        if(array_key_exists('forms', $config))
        {
            $forms = $config['forms'];

            foreach($forms as $form_name => $form_array)
            {
                $mail_fields = [];

                $form_owns = array_merge([
                        'name' => 'string',
                        'from' => 'string',
                        'subject' => 'string',
                        'username' => 'string',
                        'password' => 'string',
                        'to' => 'string'],
                    $domain_owns);
                $form_refs = [];

                if(!is_array($form_array))
                {
                    continue;
                }

                foreach($form_array as $form_key => $form_value)
                {
                    if($form_key === 'form_fields')
                    {
                        $mail_fields = $form_value;
                    }
                    elseif(is_string($form_key)) //Возможно имя типа
                    {
                        if(in_array($form_key, $c_names))
                        {
                            foreach($form_value as $own_name)
                            {
                                $form_owns[$own_name] = $form_key;
                            }
                        }
                        elseif(in_array($form_key, $b_names))
                        {
                            foreach($form_value as $own_name)
                            {
                                $form_owns[$own_name] = $form_key;
                            }
                        }
                        elseif(in_array($form_key, $a_names))
                        {
                            foreach($form_value as $ref_name)
                            {
                                $form_refs[$ref_name] = $form_key;
                            }
                        }
                    }
                }

                $formMan = new ATypeManifest('feedback', $form_name, TypeRank::BLOCK, $form_owns, $form_refs);

                $manifests->addManifest($formMan);

                //------------------------------------------------
                //Группа для писем:

                $mail_owns = array_merge([
                    'id' => 'int',
                    'name' => 'string',
                    'from' => 'string',
                    'subject' => 'string',
                    'to' => 'string',
                    'username' => 'string',
                    'email' => 'string',
                    'body' => 'text',
                    'mailed' => 'bool',
                    'report' => 'string'],
                    $domain_owns);

                $mail_refs = ['block_name' => $form_name];

                foreach($mail_fields as $mail_key => $mail_value)
                {
                    if(is_string($mail_key))
                    {
                        if(in_array($mail_key, $c_names))
                        {
                            foreach($mail_value as $own_name)
                            {
                                $mail_owns[$own_name] = $mail_key;
                            }
                        }
                        elseif(in_array($mail_key, $b_names))
                        {
                            foreach($mail_value as $own_name)
                            {
                                $mail_owns[$own_name] = $mail_key;
                            }
                        }
                        elseif(in_array($mail_key, $a_names))
                        {
                            foreach($mail_value as $ref_name)
                            {
                                $mail_refs[$ref_name] = $mail_key;
                            }
                        }
                    }
                }

                $mailMan = new ATypeManifest(
                    'feedback',
                    $form_name.'_mail',
                    TypeRank::GROUP,
                    $mail_owns,
                    $mail_refs);

                $manifests->addManifest($mailMan);

                //----------------------------------------------
                $mailto_owns = [
                        'id' => 'int',
                        'name' => 'string',
                        'to' => 'string'];

                $mailto_refs = ['block_name' => $form_name];

                $mailtoMan = new ATypeManifest(
                    'feedback',
                    $form_name.'_mailto',
                    TypeRank::GROUP,
                    $mailto_owns,
                    $mailto_refs);

                $manifests->addManifest($mailtoMan);

            }
        }


        return $manifests;
    }

}
