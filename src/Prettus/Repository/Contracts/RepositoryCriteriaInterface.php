<?php
namespace Prettus\Repository\Contracts;

use Illuminate\Support\Collection;


/**
 * Interface RepositoryCriteriaInterface
 * @package Prettus\Repository\Contracts
 * @author Anderson Andrade <contato@andersonandra.de>
 */
interface RepositoryCriteriaInterface
{

    /**
     * Push Criteria for filter the query
     *
     * @param $criteria
     *
     * @return $this
     */
    public function pushCriteria($criteria): static;

    /**
     * Pop Criteria
     *
     * @param $criteria
     *
     * @return $this
     */
    public function popCriteria($criteria): static;

    /**
     * Get Collection of Criteria
     *
     * @return Collection
     */
    public function getCriteria(): Collection;

    /**
     * Find data by Criteria
     *
     * @param CriteriaInterface $criteria
     *
     * @return mixed
     */
    public function getByCriteria(CriteriaInterface $criteria): mixed;

    /**
     * Skip Criteria
     *
     * @param bool $status
     *
     * @return $this
     */
    public function skipCriteria(bool $status = true): static;

    /**
     * Reset all Criterias
     *
     * @return $this
     */
    public function resetCriteria(): static;
}
