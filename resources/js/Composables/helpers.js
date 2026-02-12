/**
 * TindaPOS Composables — shared utility functions for Vue components.
 * Philippine Peso formatting, dates, cart logic.
 */

/**
 * Format a number as Philippine Peso.
 */
export function formatPeso(amount) {
    const num = parseFloat(amount) || 0
    return '₱' + num.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

/**
 * Format a date string to a readable format.
 */
export function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

/**
 * Format a date string with time.
 */
export function formatDateTime(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

/**
 * Get today's date in YYYY-MM-DD format.
 */
export function today() {
    return new Date().toISOString().split('T')[0]
}

/**
 * Debounce a function call.
 */
export function debounce(fn, delay = 300) {
    let timer
    return (...args) => {
        clearTimeout(timer)
        timer = setTimeout(() => fn(...args), delay)
    }
}
