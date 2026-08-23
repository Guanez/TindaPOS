/**
 * The words this shop uses for its own things.
 *
 * Shared from the server by store type, so a cafe reads "Menu" where a
 * sari-sari store reads "Inventory". Pages ask for the word; they never ask
 * what kind of shop they are in.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const FALLBACK = { catalogue: 'Inventory', item: 'Product', items: 'Products' };

export function useVocabulary() {
    const page = usePage();

    return computed(() => page.props.words ?? FALLBACK);
}
