<script setup>
defineProps({ sections: { type: Array, default: () => [] } })
const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0))
function display(section, column, value) {
    if (section.percent === column) return `${Number(value || 0).toFixed(2)}%`
    return section.money.includes(column) ? money(value) : value
}
</script>

<template>
    <table v-for="(section, index) in sections" :key="index" class="report-table mt-5 w-full table-fixed border-collapse text-[10px] text-black">
        <thead>
            <tr><th :colspan="section.headers.length" class="border border-black bg-white px-2 py-2 text-left font-bold uppercase">{{ section.title }}</th></tr>
            <tr><th v-for="(header, column) in section.headers" :key="column" class="break-words border border-black bg-white px-2 py-2 text-left" :class="{ 'text-right': section.money.includes(column) || section.percent === column }">{{ header }}</th></tr>
        </thead>
        <tbody>
            <tr v-for="(row, rowIndex) in section.rows" :key="rowIndex">
                <td v-for="(value, column) in row" :key="column" class="break-words border border-black px-2 py-2 align-top" :class="{ 'text-right tabular-nums': section.money.includes(column) || section.percent === column }">{{ display(section, column, value) }}</td>
            </tr>
            <tr v-if="!section.rows.length"><td :colspan="section.headers.length" class="border border-black px-2 py-5 text-center text-gray-500">No records match the selected report filters.</td></tr>
        </tbody>
        <tfoot v-if="section.totalLabel">
            <tr class="bg-gray-100 font-bold"><td v-for="(_, column) in section.headers" :key="column" class="border border-black px-2 py-2" :class="{ 'text-right': column > 0 }">{{ column === 0 ? section.totalLabel : section.totals[column] === undefined ? '' : display(section, column, section.totals[column]) }}</td></tr>
        </tfoot>
    </table>
</template>
