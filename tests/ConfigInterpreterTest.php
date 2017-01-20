<?php

namespace Interpro\Feedback\Test;

use Interpro\Core\Taxonomy\TypesForecastList;
use Interpro\Feedback\FeedbackConfigInterpreter;

class ConfigInterpreterTest extends \PHPUnit_Framework_TestCase
{
    private function getTaxonomy()
    {
        $taxonomyFactory = new \Interpro\Core\Taxonomy\Factory\TaxonomyFactory();

        $family = 'scalar';
        $name = 'string';
        $manC1 = new \Interpro\Core\Taxonomy\Manifests\CTypeManifest($family, $name, [], []);

        $family = 'scalar';
        $name = 'int';
        $manC2 = new \Interpro\Core\Taxonomy\Manifests\CTypeManifest($family, $name, [], []);

        $family = 'scalar';
        $name = 'bool';
        $manC3 = new \Interpro\Core\Taxonomy\Manifests\CTypeManifest($family, $name, [], []);

        $family = 'scalar';
        $name = 'text';
        $manC4 = new \Interpro\Core\Taxonomy\Manifests\CTypeManifest($family, $name, [], []);

        $family = 'scalar';
        $name = 'timestamp';
        $manC5 = new \Interpro\Core\Taxonomy\Manifests\CTypeManifest($family, $name, [], []);

        $manifestsCollection = new \Interpro\Core\Taxonomy\Collections\ManifestsCollection();
        $manifestsCollection->addManifest($manC1);
        $manifestsCollection->addManifest($manC2);
        $manifestsCollection->addManifest($manC3);
        $manifestsCollection->addManifest($manC4);
        $manifestsCollection->addManifest($manC5);

        $fCList = new TypesForecastList();

        $fCList->registerCTypeName('string');
        $fCList->registerCTypeName('text');
        $fCList->registerCTypeName('int');
        $fCList->registerCTypeName('bool');
        $fCList->registerCTypeName('timestamp');

        $interpreter = new FeedbackConfigInterpreter($fCList);

        $fbManifestCollection = $interpreter->interpretConfig([
            'feedback' => [
                //Предпоследнее место где формы смотрят: from, host, port, encryption, password, to
                //Здесь можно настроить конфиг из админки
                //доп. поля формате qs конфига
                'string' => ['descr1', 'descr2'],
                'int' => ['number1', 'number2']
            ],
            'mailfromac' => [
                //Подчинен блоку feedback
                //Группа автоподстановки по домену почты отправителя from значений host, port и encryption отправителя
            ],
            'forms' => [
                //Блоки форм
                //предопределенные поля: from, subject, host, port, encryption, password, to
                'form1' => [
                    //доп. поля интерфейса и настройки формы в формате qs конфига
                    //form1_mailto - подгруппа с одним полем mailto
                    'string' => ['descr5', 'descr6'],
                    'int' => ['number5', 'number6'],

                    'form_fields' => [
                        //Каждой форме добавляется подгруппа с именем имяформы_mails, здесь настраивать поля для этой подгруппы
                        //from, subject, host, port, encryption, to, username, email, body, mailed  -  по умолчанию +
                        //доп. поля формы в формате qs конфига - для значений полей от пользователя сайта
                        'string' => ['descr7', 'descr8'],
                        'int' => ['number7', 'number8']
                    ]
                ],

                'form2' => [
                    'string' => ['descr9', 'descr10'],
                    'int' => ['number9', 'number10'],
                    'form_fields' => [
                        'string' => ['descr11', 'descr12'],
                        'int' => ['number11', 'number12']
                    ]
                ]
            ]
        ]);

        foreach($fbManifestCollection as $manifest)
        {
            $manifestsCollection->addManifest($manifest);
        }

        $taxonomy = $taxonomyFactory->createTaxonomy($manifestsCollection);

        return $taxonomy;
    }

    public function testInterpretedTaxonomy()
    {
        $tax = $this->getTaxonomy();

        //-----------------------------------------
        $feedback = $tax->getType('feedback');

        $must_be = '{name:string,from:string,to:string,subject:string,username:string,password:string,host:string,port:string,encryption:string,descr1:string,descr2:string,number1:int,number2:int,}';

        $we_have = '{';

        foreach($feedback->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);


        //-----------------------------------------группа автоподстановки
        $mailfromac = $tax->getType('mailfromac');

        $must_be = '{id:int,name:string,domain:string,host:string,port:string,encryption:string,block_name:feedback,superior:feedback,}';

        $we_have = '{';

        foreach($mailfromac->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        foreach($mailfromac->getRefs() as $ref)
        {
            $we_have .= $ref->getName().':'.$ref->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);


        //-----------------------------------------блок формы 1
        $form1 = $tax->getType('form1');
        $must_be = '{name:string,from:string,subject:string,username:string,password:string,to:string,host:string,port:string,encryption:string,descr5:string,descr6:string,number5:int,number6:int,}';

        $we_have = '{';

        foreach($form1->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);

        //--------------------------------------------

        $form1_mail = $tax->getType('form1_mail');

        $must_be = '{id:int,name:string,from:string,subject:string,to:string,username:string,email:string,body:text,mailed:bool,report:string,updated_at:timestamp,created_at:timestamp,host:string,port:string,encryption:string,descr7:string,descr8:string,number7:int,number8:int,block_name:form1,superior:form1,}';

        $we_have = '{';

        foreach($form1_mail->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        foreach($form1_mail->getRefs() as $ref)
        {
            $we_have .= $ref->getName().':'.$ref->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);

        //--------------------------------------------

        $form1_mailto = $tax->getType('form1_mailto');

        $must_be = '{id:int,name:string,to:string,block_name:form1,superior:form1,}';

        $we_have = '{';

        foreach($form1_mailto->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        foreach($form1_mailto->getRefs() as $ref)
        {
            $we_have .= $ref->getName().':'.$ref->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);

        //--------------------------------------------
        //--------------------------------------------
        //--------------------------------------------

        $form2 = $tax->getType('form2');
        $form2_mail = $tax->getType('form2_mail');

        //--------------------------------------------
        //надеемся что все нормально со 2й формой, проверим только одну группу
        //--------------------------------------------

        $form2_mailto = $tax->getType('form2_mailto');

        $must_be = '{id:int,name:string,to:string,block_name:form2,superior:form2,}';

        $we_have = '{';

        foreach($form2_mailto->getOwns() as $own)
        {
            $we_have .= $own->getName().':'.$own->getFieldTypeName().',';
        }

        foreach($form2_mailto->getRefs() as $ref)
        {
            $we_have .= $ref->getName().':'.$ref->getFieldTypeName().',';
        }

        $we_have .= '}';

        $this->assertEquals($we_have, $must_be);
    }

}
