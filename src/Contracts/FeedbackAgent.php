<?php

namespace Interpro\Feedback\Contracts;

interface FeedbackAgent
{
    /**
     * @param string $form
     * @param string $template
     * @return void
     */
    public function setBodyTemplate($form, $template);

    /**
     * @param string $form
     * @param array $fields
     * @return string
     */
    public function mail($form, array $fields);
}
