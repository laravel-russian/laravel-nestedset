<?php

namespace Kalnoy\Nestedset;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TNodeModel of \Illuminate\Database\Eloquent\Model&\Kalnoy\Nestedset\Node
 * @phpstan-extends EloquentCollection<TNodeModel>
 */
class Collection extends EloquentCollection
{
	/**
	 * @param iterable<TNodeModel> $items
	 */
	public final function __construct($items = [])
	{
		parent::__construct($items);
	}

	/**
     * Fill `parent` and `children` relationships for every node in the collection.
     *
     * This will overwrite any previously set relations.
     *
     * @return $this
     */
    public function linkNodes(): static
    {
        if ($this->isEmpty()) return $this;

        $groupedNodes = $this->groupBy($this->first()->getParentIdName());

        /** @var TNodeModel $node */
        foreach ($this->items as $node) {
            if ($node->getParentId() !== null) {
                $node->setRelation('parent', null);
            }

            $children = $groupedNodes->get($node->getKey(), [ ]);

            /** @var TNodeModel $child */
            foreach ($children as $child) {
                $child->setRelation('parent', $node);
            }

            $node->setRelation('children', EloquentCollection::make($children));
        }

        return $this;
    }

    /**
     * Build a tree from a list of nodes. Each item will have set children relation.
     *
     * To successfully build tree "id", "_lft" and "parent_id" keys must present.
     *
     * If `$root` is provided, the tree will contain only descendants of that node.
     *
     * @param ?TNodeModel $root
     *
     * @return static
     */
    public function toTree(?Node $root = null): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $this->linkNodes();

        $items = [ ];

        $rootId = $this->getRootNodeId($root);

        /** @var TNodeModel $node */
        foreach ($this->items as $node) {
            if ($node->getParentId() === $rootId) {
                $items[] = $node;
            }
        }

        return new static($items);
    }

    /**
     * @param ?TNodeModel $root
     *
     * @return int|string|null
     */
    protected function getRootNodeId(?Model $root = null): int|string|null
    {
        if ($root !== null) {
            return $root->getKey();
        }

        // If root node is not specified we take parent id of node with
        // least lft value as root node id.
        $leastValue = null;

        /** @var TNodeModel $node */
        foreach ($this->items as $node) {
            if ($leastValue === null || $node->getLft() < $leastValue) {
                $leastValue = $node->getLft();
                $root = $node->getParentId();
            }
        }

        return $root;
    }

    /**
     * Build a list of nodes that retain the order that they were pulled from
     * the database.
     *
     * @param TNodeModel|null $root
     *
     * @return static
     */
    public function toFlatTree(?Model $root = null): static
    {
        $result = new static();

        if ($this->isEmpty()) return $result;

        $groupedNodes = $this->groupBy($this->first()->getParentIdName());

        return $result->flattenTree($groupedNodes, $this->getRootNodeId($root));
    }

    /**
     * Flatten a tree into a non-recursive array.
     *
     * @param BaseCollection<BaseCollection<TNodeModel>> $groupedNodes
     * @param int|string|null $parentId
     *
     * @return $this
     */
    protected function flattenTree(BaseCollection $groupedNodes, int|string|null $parentId): static
    {
        foreach ($groupedNodes->get($parentId, []) as $node) {
            $this->push($node);

            $this->flattenTree($groupedNodes, $node->getKey());
        }

        return $this;
    }

}