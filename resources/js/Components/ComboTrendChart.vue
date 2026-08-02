<script setup>
import { Chart, registerables } from 'chart.js'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

Chart.register(...registerables)

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'label' },
    series: { type: Array, default: () => [] },
    showHeader: { type: Boolean, default: true },
    showLegend: { type: Boolean, default: true },
    isActive: { type: Boolean, default: true },
    tooltipTitleKey: { type: String, default: 'tooltipTitle' },
})

const rootRef = ref(null)
const canvasRef = ref(null)
const chartReady = ref(false)
let chartInstance = null
let revealProgress = 0

const COLORS = {
    navy: '#1e293b',
    teal: '#0f766e',
    gold: '#d4a843',
    grid: '#e5e7eb',
    text: '#475569',
}

const chartData = computed(() => {
    const items = Array.isArray(props.items) ? props.items : []
    const labels = items.map((item) => {
        const label = item?.[props.labelKey]
        if (label !== undefined && label !== null && String(label).trim() !== '') return String(label)
        if (item?.month !== undefined && item?.year !== undefined) return `${item.month} ${item.year}`
        if (item?.month_label !== undefined && item?.year !== undefined) return `${item.month_label} ${item.year}`
        if (item?.year !== undefined) return String(item.year)
        return ''
    })
    return { items, labels }
})

function formatMoney(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0))
}

function seriesValue(item, key) {
    if (!item) return 0
    if (key === 'expenditure' && item.expenditure !== undefined) return Number(item.expenditure || 0)
    if (key === 'expenditure' && item.expense !== undefined) return Number(item.expense || 0)
    if (key === 'expense' && item.expense !== undefined) return Number(item.expense || 0)
    if (key === 'expense' && item.expenditure !== undefined) return Number(item.expenditure || 0)
    if (key === 'utilization') return Number(item.utilization || 0)
    if (key === 'balance') return Number(item.balance || 0)
    if (item[key] !== undefined) return Number(item[key] || 0)
    return 0
}

function buildChart() {
    if (!canvasRef.value) return
    const ctx = canvasRef.value.getContext('2d')
    if (!ctx) return

    if (chartInstance) {
        chartInstance.destroy()
        chartInstance = null
    }

    revealProgress = 0
    const items = chartData.value.items
    const seriesDefs = Array.isArray(props.series) ? props.series : []
    const colorMap = Object.fromEntries(seriesDefs.map((s) => [s.key, s.color || COLORS.navy]))

    const datasets = seriesDefs.map((series, index) => {
        const values = items.map((item) => seriesValue(item, series.key))
        const isLine = series.type === 'line'
        const color = series.color || (isLine ? COLORS.gold : COLORS.navy)
        const fade = series.fadeColor || color
        const gradient = ctx.createLinearGradient(0, 0, 0, 320)
        gradient.addColorStop(0, color)
        gradient.addColorStop(1, fade)

        if (isLine) {
            return {
                type: 'line',
                label: series.label,
                data: values,
                borderColor: color,
                backgroundColor: gradient,
                yAxisID: series.yAxisID || 'y2',
                tension: 0.42,
                cubicInterpolationMode: 'monotone',
                pointRadius: 5.5,
                pointHoverRadius: 8,
                pointBorderWidth: 2.5,
                pointBorderColor: '#ffffff',
                pointBackgroundColor: color,
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: '#ffffff',
                pointHitRadius: 18,
                fill: {
                    target: 'origin',
                    above: 'rgba(212, 168, 67, 0.12)',
                },
                order: index,
            }
        }

        return {
            type: 'bar',
            label: series.label,
            data: values,
            backgroundColor: gradient,
            borderColor: color,
            borderWidth: 0,
            borderRadius: 10,
            borderSkipped: false,
            barPercentage: series.barPercentage ?? 0.72,
            categoryPercentage: series.categoryPercentage ?? 0.72,
            maxBarThickness: series.maxBarThickness ?? 56,
            yAxisID: series.yAxisID || 'y',
            order: index + 1,
        }
    })

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.value.labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 22, right: 18, bottom: 10, left: 10 },
            },
            animation: {
                duration: 2200,
                easing: 'easeOutQuart',
                delay(context) {
                    if (context.type !== 'data') return 0
                    const base = context.dataset.type === 'bar' ? 180 : 140
                    return context.dataIndex * base
                },
                onProgress(animation) {
                    revealProgress = animation.currentStep / animation.numSteps
                },
                onComplete() {
                    revealProgress = 1
                },
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: false,
                    external(context) {
                        const rootEl = rootRef.value
                        if (!rootEl) return

                        let tooltipEl = rootEl.querySelector('#combo-trend-tooltip')
                        if (!tooltipEl) {
                            tooltipEl = document.createElement('div')
                            tooltipEl.id = 'combo-trend-tooltip'
                            tooltipEl.className = 'pointer-events-none absolute z-30 w-72 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-700 shadow-2xl transition-opacity duration-150'
                            rootEl.appendChild(tooltipEl)
                        }

                        const model = context.tooltip
                        if (!model || model.opacity === 0) {
                            tooltipEl.style.opacity = '0'
                            return
                        }

                        const itemIndex = model.dataPoints?.[0]?.dataIndex ?? 0
                        const item = chartData.value.items[itemIndex] || {}
                        const title = item?.[props.tooltipTitleKey] || item?.[props.labelKey] || chartData.value.labels[itemIndex] || ''

                        const rows = []
                        const seen = new Set()
                        for (const series of seriesDefs) {
                            if (series.type === 'line') continue
                            if (seen.has(series.key)) continue
                            seen.add(series.key)
                            const raw = seriesValue(item, series.key)
                            rows.push([series.label || series.key, formatMoney(raw), `color:${colorMap[series.key] || COLORS.navy}`])
                        }
                        if ('balance' in item) rows.push(['Balance', formatMoney(item.balance), 'color:#0f172a'])
                        if ('utilization' in item) rows.push(['Util Rate', `${Number(item.utilization || 0).toFixed(1)}%`, `color:${COLORS.gold}`])

                        tooltipEl.innerHTML = `
                            <div class="border-b border-slate-200 pb-2 mb-2 flex items-center justify-between gap-3">
                                <div class="font-bold text-slate-900">${title}</div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">${seriesDefs.length === 3 ? 'Monthly Trend' : 'Annual Trend'}</div>
                            </div>
                            <div class="space-y-1.5">
                                ${rows.map(([label, value, color]) => `<div class="flex items-center justify-between gap-4"><span>${label}</span><span class="font-bold" style="${color}">${value}</span></div>`).join('')}
                            </div>
                        `

                        const canvasRect = context.chart.canvas.getBoundingClientRect()
                        const rootRect = rootEl.getBoundingClientRect()
                        const relativeX = canvasRect.left - rootRect.left + model.caretX
                        const relativeY = canvasRect.top - rootRect.top + model.caretY
                        const tooltipWidth = 288
                        const minLeft = 12 + tooltipWidth / 2
                        const maxLeft = Math.max(minLeft, rootRect.width - 12 - tooltipWidth / 2)
                        const clampedLeft = Math.min(Math.max(relativeX, minLeft), maxLeft)
                        tooltipEl.style.opacity = '1'
                        tooltipEl.style.left = `${clampedLeft}px`
                        tooltipEl.style.top = `${Math.max(12, relativeY - 118)}px`
                        tooltipEl.style.transform = 'translateX(-50%)'
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: COLORS.text,
                        font: {
                            family: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                            size: 12,
                            weight: '700',
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.max(...datasets.flatMap((ds) => ds.data || []), 100000) * 1.12,
                    grace: '14%',
                    grid: {
                        color: COLORS.grid,
                        borderDash: [4, 6],
                    },
                    ticks: {
                        color: COLORS.text,
                        font: {
                            family: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                            size: 11,
                            weight: '600',
                        },
                        callback(value) {
                            return formatMoney(value)
                        },
                    },
                },
                y2: {
                    beginAtZero: true,
                    position: 'right',
                    display: true,
                    min: 0,
                    max: 110,
                    suggestedMax: 110,
                    ticks: {
                        color: COLORS.text,
                        font: {
                            family: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                            size: 11,
                            weight: '600',
                        },
                        stepSize: 10,
                        callback(value) {
                            if (value > 100) return ''
                            return `${value}%`
                        },
                    },
                    grid: { drawOnChartArea: false },
                },
            },
        },
        plugins: [{
            id: 'combo-line-reveal',
            beforeDatasetDraw(chart, args) {
                if (args.meta.type !== 'line') return
                const { ctx, chartArea } = chart
                if (!chartArea) return
                const revealWidth = Math.max(0, chartArea.width * Math.min(1, revealProgress || 0))
                ctx.save()
                ctx.beginPath()
                ctx.rect(chartArea.left, chartArea.top, revealWidth, chartArea.height)
                ctx.clip()
            },
            afterDatasetDraw(chart, args) {
                if (args.meta.type !== 'line') return
                chart.ctx.restore()
            },
        }],
    })

    chartInstance.reset()
    chartInstance.update()
}

async function refreshChart() {
    chartReady.value = false
    await nextTick()
    requestAnimationFrame(() => {
        buildChart()
        requestAnimationFrame(() => {
            chartReady.value = true
        })
    })
}

onMounted(refreshChart)

watch(() => props.items, () => {
    if (props.isActive) refreshChart()
}, { deep: true })

watch(() => props.isActive, (isActive) => {
    if (isActive) refreshChart()
})

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy()
        chartInstance = null
    }
    const tooltipEl = document.getElementById('combo-trend-tooltip')
    if (tooltipEl) tooltipEl.remove()
})
</script>

<template>
    <div
        ref="rootRef"
        :class="[
            'relative rounded-lg border border-slate-200 bg-slate-50/60 overflow-hidden',
            showHeader ? 'h-[24rem] p-4' : 'h-[22rem] p-3',
            chartReady ? 'chart-shell--ready' : 'chart-shell--loading',
        ]"
    >
        <div v-if="showHeader" class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">{{ title }}</h3>
                <p class="text-xs text-slate-500">{{ subtitle }}</p>
            </div>
            <div v-if="showLegend" class="flex items-center gap-4 text-xs font-semibold text-slate-600">
                <span v-for="item in series" :key="item.label" class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: item.color || COLORS.navy }"></span>
                    {{ item.label }}
                </span>
            </div>
        </div>

        <div :class="showHeader ? 'relative h-[calc(100%-3.75rem)]' : 'relative h-[calc(100%-0.25rem)]'">
            <canvas ref="canvasRef"></canvas>
        </div>
    </div>
</template>

<style scoped>
.chart-shell--loading {
    opacity: 0;
    transform: translateY(10px) scale(0.985);
}

.chart-shell--ready {
    animation: chart-shell-enter 700ms ease-out both;
}

@keyframes chart-shell-enter {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
