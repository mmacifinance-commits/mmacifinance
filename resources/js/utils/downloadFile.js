function filenameFromDisposition(disposition, fallback) {
    const match = String(disposition || '').match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
    return match ? decodeURIComponent(match[1]) : fallback
}

async function messageFromResponse(response) {
    const contentType = response.headers.get('content-type') || ''
    const text = await response.text().catch(() => '')

    if (contentType.includes('application/json')) {
        try {
            const json = JSON.parse(text)
            const validation = json.errors ? Object.values(json.errors).flat().join(' ') : ''
            return validation || json.message || json.error || `Export failed with status ${response.status}.`
        } catch {
            return `Export failed with status ${response.status}.`
        }
    }

    if (text) {
        const cleaned = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
        return cleaned.slice(0, 240) || `Export failed with status ${response.status}.`
    }

    return `Export failed with status ${response.status}.`
}

export async function downloadFile(url, fallbackFilename = 'export.xlsx') {
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/json, text/plain, */*',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })

    if (!response.ok) {
        throw new Error(await messageFromResponse(response))
    }

    const blob = await response.blob()
    const filename = filenameFromDisposition(response.headers.get('content-disposition'), fallbackFilename)
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(objectUrl)
}
