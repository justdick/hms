import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function DrugIndex() {
    useEffect(() => {
        router.visit('/pharmacy/inventory');
    }, []);

    return null;
}
