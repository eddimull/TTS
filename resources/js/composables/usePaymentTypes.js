/**
 * Composable for payment type utilities
 * Provides centralized payment type mapping, labels, icons, and colors
 */
export function usePaymentTypes() {
    const paymentTypeMap = {
        cash: { label: 'Cash', icon: 'pi pi-money-bill', color: 'green' },
        check: { label: 'Check', icon: 'pi pi-file', color: 'blue' },
        portal: { label: 'Client Portal', icon: 'pi pi-globe', color: 'purple' },
        venmo: { label: 'Venmo', icon: 'pi pi-mobile', color: 'cyan' },
        zelle: { label: 'Zelle', icon: 'pi pi-mobile', color: 'indigo' },
        invoice: { label: 'Invoice', icon: 'pi pi-file-edit', color: 'orange' },
        wire: { label: 'Wire Transfer', icon: 'pi pi-building', color: 'teal' },
        credit_card: { label: 'Credit Card', icon: 'pi pi-credit-card', color: 'pink' },
        other: { label: 'Other', icon: 'pi pi-question-circle', color: 'gray' },
    };

    const getPaymentTypeLabel = (type) => {
        return paymentTypeMap[type]?.label || type;
    };

    const getPaymentTypeIcon = (type) => {
        return paymentTypeMap[type]?.icon || 'pi pi-question-circle';
    };

    const getPaymentTypeColor = (type) => {
        return paymentTypeMap[type]?.color || 'gray';
    };

    const getPaymentType = (type) => {
        return paymentTypeMap[type] || {
            label: type,
            icon: 'pi pi-question-circle',
            color: 'gray'
        };
    };

    return {
        paymentTypeMap,
        getPaymentTypeLabel,
        getPaymentTypeIcon,
        getPaymentTypeColor,
        getPaymentType,
    };
}
