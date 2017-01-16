<?php

namespace Interpro\Feedback\Db;

use Interpro\Core\Contracts\Taxonomy\Fields\Field;
use Interpro\Extractor\Contracts\Db\Joiner;
use Interpro\Extractor\Contracts\Db\JoinMediator;
use Interpro\Feedback\Exception\FeedbackException;

class FeedbackJoiner implements Joiner
{
    private $joinMediator;

    public function __construct(JoinMediator $joinMediator)
    {
        $this->joinMediator = $joinMediator;
    }

    /**
     * @return string
     */
    public function getFamily()
    {
        return 'feedback';
    }

    /**
     * @param \Interpro\Core\Contracts\Taxonomy\Fields\Field $field
     * @param array $join_array
     *
     * @return mixed
     */
    public function joinByField(Field $field, $join_array)
    {
        throw new FeedbackException('Возможность сортировать через ссылку из пакета feedback не поддерживается!');
    }

}
