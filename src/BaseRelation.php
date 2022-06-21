<?php

namespace Kalnoy\Nestedset;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

/**
 * @template TNodeModel of \Illuminate\Database\Eloquent\Model&\Kalnoy\Nestedset\Node
 * @phpstan-extends Relation<TNodeModel>
 */
abstract class BaseRelation extends Relation
{
    /**
     * @var QueryBuilder<TNodeModel>
     */
    protected $query;

    /**
     * @var TNodeModel
     */
    protected $parent;

	/**
	 * @var TNodeModel
	 */
	protected $related;

    /**
     * AncestorsRelation constructor.
     *
     * @param QueryBuilder<TNodeModel> $builder
     * @param TNodeModel $model
     */
    public function __construct(QueryBuilder $builder, Model $model)
    {
        parent::__construct($builder, $model);
    }

    /**
     * @param TNodeModel $model
     * @param TNodeModel $related
     *
     * @return bool
     */
    abstract protected function matches(Model $model, Model $related): bool;

    /**
     * @param QueryBuilder<TNodeModel> $query
     * @param TNodeModel $model
     *
     * @return void
     */
    abstract protected function addEagerConstraint(QueryBuilder $query, Model $model): void;

    /**
     * @param string $hash
     * @param string $table
     * @param string $lft
     * @param string $rgt
     *
     * @return string
     */
    abstract protected function relationExistenceCondition(string $hash, string $table, string $lft, string $rgt): string;

    /**
     * @param EloquentBuilder<TNodeModel> $query
     * @param EloquentBuilder<TNodeModel> $parentQuery
     * @param array<string> $columns
     *
     * @return QueryBuilder<TNodeModel>
     */
    public function getRelationExistenceQuery(EloquentBuilder $query, EloquentBuilder $parentQuery, $columns = ['*']): QueryBuilder
    {
        $query = $this->getParent()->replicate()->newScopedQuery()->select($columns);

        $table = $query->getModel()->getTable();

        $query->from($table.' as '.$hash = $this->getRelationCountHash());

        $query->getModel()->setTable($hash);

        $grammar = $query->getQuery()->getGrammar();

        $condition = $this->relationExistenceCondition(
            $grammar->wrapTable($hash),
            $grammar->wrapTable($table),
            $grammar->wrap($this->parent->getLftName()),
            $grammar->wrap($this->parent->getRgtName()));

        return $query->whereRaw($condition);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array<TNodeModel> $models
     * @param  string $relation
     *
     * @return array<TNodeModel>
     */
    public function initRelation(array $models, $relation): array
    {
        return $models;
    }

    /**
     * Get a relationship join table hash.
     *
     * @param  bool $incrementJoinCount
     * @return string
     */
    public function getRelationCountHash($incrementJoinCount = true): string
    {
        return 'nested_set_'.($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Get the results of the relationship.
     *
     * @return Collection<TNodeModel>
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array<TNodeModel> $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $this->parent->applyNestedSetScope($this->query);

        $this->query->whereNested(function (Builder $inner) use ($models) {
            // We will use this query in order to apply constraints to the
            // base query builder
            $outer = $this->parent->newQuery()->setQuery($inner);

            foreach ($models as $model) {
                $this->addEagerConstraint($outer, $model);
            }
        });
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array<TNodeModel> $models
     * @param  EloquentCollection<TNodeModel> $results
     * @param  string $relation
     *
     * @return array<TNodeModel>
     */
    public function match(array $models, EloquentCollection $results, $relation): array
    {
        foreach ($models as $model) {
            $related = $this->matchForModel($model, $results);

            $model->setRelation($relation, $related);
        }

        return $models;
    }

    /**
     * @param TNodeModel $model
     * @param EloquentCollection<TNodeModel> $results
     *
     * @return Collection<TNodeModel>
     */
    protected function matchForModel(Model $model, EloquentCollection $results): Collection
    {
        $result = $this->related->newCollection();

        foreach ($results as $related) {
            if ($this->matches($model, $related)) {
                $result->push($related);
            }
        }

        return $result;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName(): string
    {
        // Return a stub value for relation
        // resolvers which need this function.
        return NestedSet::PARENT_ID;
    }
}
