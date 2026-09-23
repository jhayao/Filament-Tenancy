{{-- Ship row layout with the page: host applications need no Tailwind source scan. --}}
<style>
    .fi-tenancy-members {
        display: grid;
        gap: 1.5rem;
        min-width: 0;
    }

    .fi-tenancy-members .fi-tenancy-members-row {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding-block: 1rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .fi-tenancy-members .fi-tenancy-members-row:first-child { padding-top: 0; }
    .fi-tenancy-members .fi-tenancy-members-row:last-child { padding-bottom: 0; border-bottom: 0; }

    .fi-tenancy-members .fi-tenancy-members-identity {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        flex: 1;
    }

    .fi-tenancy-members .fi-avatar { flex-shrink: 0; }
    .fi-tenancy-members .fi-tenancy-members-details { min-width: 0; display: grid; gap: 0.25rem; }

    .fi-tenancy-members .fi-tenancy-members-labels,
    .fi-tenancy-members .fi-tenancy-members-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .fi-tenancy-members .fi-tenancy-members-name {
        font-weight: 500;
        overflow-wrap: anywhere;
        color: var(--gray-950);
    }

    .fi-tenancy-members .fi-tenancy-members-meta {
        font-size: 0.875rem;
        overflow-wrap: anywhere;
        color: var(--gray-500);
    }

    .dark .fi-tenancy-members .fi-tenancy-members-row { border-color: rgb(255 255 255 / 0.1); }
    .dark .fi-tenancy-members .fi-tenancy-members-name { color: var(--gray-50); }
    .dark .fi-tenancy-members .fi-tenancy-members-meta { color: var(--gray-400); }

    @media (min-width: 48rem) {
        .fi-tenancy-members .fi-tenancy-members-row {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }

        .fi-tenancy-members .fi-tenancy-members-actions {
            justify-content: flex-end;
            max-width: 65%;
        }
    }
</style>
