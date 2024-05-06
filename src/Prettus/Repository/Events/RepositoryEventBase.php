<?php
namespace Prettus\Repository\Events;

use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class RepositoryEventBase
 * @package Prettus\Repository\Events
 * @author Anderson Andrade <contato@andersonandra.de>
 */
abstract class RepositoryEventBase
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var RepositoryInterface
     */
    protected RepositoryInterface $repository;

    /**
     * @var string
     */
    protected string $action;

    /**
     * @param RepositoryInterface $repository
     * @param Model|null $model
     */
    public function __construct(RepositoryInterface $repository, Model $model = null)
    {
        $this->repository = $repository;
        $this->model = $model;
    }

    /**
     * @return Model|null
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * @return RepositoryInterface
     */
    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }
}
