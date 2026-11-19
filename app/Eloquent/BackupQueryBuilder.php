<?php

namespace App\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class BackupQueryBuilder extends Builder
{
    public function nonFailed(): self
    {
        $this->where(function (Builder $query) {
            $query
                ->whereNull('completed_at')
                ->orWhere('is_successful', true);
        });

        return $this;
    }

    /**
     * Backups kept after their server was deleted.
     */
    public function orphaned(): static
    {
        $this->whereNull('server_id');

        return $this;
    }

    /**
     * Backups that finished successfully and can be restored.
     */
    public function restorable(): static
    {
        $this->whereNotNull('completed_at')->where('is_successful', true);

        return $this;
    }

    /**
     * Backups on the nodes the user can access. Backups without a node fall back
     * to their server's node.
     */
    public function accessibleBy(?User $user): static
    {
        if ($user?->isRootAdmin()) {
            return $this;
        }

        $nodes = $user ? $user->accessibleNodes()->pluck('id')->all() : [];

        $this->where(fn (Builder $query) => $query
            ->whereIn('node_id', $nodes)
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('node_id')
                ->whereHas('server', fn (Builder $query) => $query->whereIn('node_id', $nodes))));

        return $this;
    }
}
