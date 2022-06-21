<?php

namespace Kalnoy\Nestedset;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TNodeModel of \Illuminate\Database\Eloquent\Model&\Kalnoy\Nestedset\Node
 * @phpstan-extends BaseRelation<TNodeModel>
 */
class DescendantsRelation extends BaseRelation
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if ( ! static::$constraints) return;

        $this->query->whereDescendantOf($this->parent)
        ->applyNestedSetScope();
    }

    /**
     * @param QueryBuilder<TNodeModel> $query
     * @param TNodeModel $model
     */
    protected function addEagerConstraint(QueryBuilder $query, Model $model): void
    {
        $query->orWhereDescendantOf($model);
    }

    /**
     * @param TNodeModel $model
     * @param TNodeModel $related
     *
     * @return bool
     */
    protected function matches(Model $model, Model $related): bool
    {
        return $related->isDescendantOf($model);
    }

    /**
     * @param string $hash
     * @param string $table
     * @param string $lft
     * @param string $rgt
     *
     * @return string
     */
    protected function relationExistenceCondition(string $hash, string $table, string $lft, string $rgt): string
    {
        return "$hash.$lft between $table.$lft + 1 and $table.$rgt";
    }
}