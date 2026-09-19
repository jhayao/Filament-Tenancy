<?php

namespace Liern\FilamentTenancy\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Expression;

class WorkspaceUsers extends BelongsToMany
{
    protected function performJoin($query = null)
    {
        $query ??= $this->query;
        if ($query->getConnection()->getDriverName() !== 'pgsql') {
            return parent::performJoin($query);
        }

        // The application owns the user key type; the membership key is always text.
        // PostgreSQL requires an explicit cast for numeric and native UUID user keys.
        $key = $query->getQuery()->getGrammar()->wrap($this->getQualifiedRelatedKeyName());
        $query->join($this->table, new Expression('CAST('.$key.' AS TEXT)'), '=', $this->getQualifiedRelatedPivotKeyName());

        return $this;
    }
}
