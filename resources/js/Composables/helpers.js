/**
 * TindaPOS Composables — shared utility functions for Vue components.
 * Dates and cart logic. Money lives in currency.js, which formats with the
 * shop's own symbol rather than a hardcoded one.
 */

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
    const now = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    // Local date — toISOString() would return the UTC day and roll over early in PH.
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
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
