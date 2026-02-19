"use client"

import { useState } from "react"
import { useBudget } from "@/lib/budget-context"
import { formatCurrency } from "@/lib/store"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  TableFooter,
} from "@/components/ui/table"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts"
import { FileText, Printer } from "lucide-react"
import { Button } from "@/components/ui/button"

export function FinancialReports() {
  const { budgets, categories, particulars, expenses, disbursements } = useBudget()
  const [selectedYear, setSelectedYear] = useState(budgets.length > 0 ? budgets[0].id : "")

  const budget = budgets.find((b) => b.id === selectedYear)

  const totalAppropriation = budget?.items.reduce((s, i) => s + i.appropriation, 0) || 0
  const totalExpenditure = budget?.items.reduce((s, i) => s + i.expenditure, 0) || 0
  const totalBalance = totalAppropriation - totalExpenditure

  const totalExpenseAmount = expenses.reduce((s, e) => s + e.amount, 0)
  const totalExpensePaid = expenses.reduce((s, e) => s + e.paid, 0)
  const totalDisbursementAmount = disbursements.reduce((s, d) => s + d.amount, 0)

  const approvedExpenses = expenses.filter((e) => e.status === "approved").length
  const pendingExpenses = expenses.filter((e) => e.status === "pending").length
  const approvedDisbursements = disbursements.filter((d) => d.status === "approved").length
  const pendingDisbursements = disbursements.filter((d) => d.status === "pending").length

  // Budget category breakdown data
  const categoryBreakdown = categories.map((cat) => {
    const items = budget?.items.filter((i) => i.categoryId === cat.id) || []
    const appropriation = items.reduce((s, i) => s + i.appropriation, 0)
    const expenditure = items.reduce((s, i) => s + i.expenditure, 0)
    const balance = appropriation - expenditure
    const utilization = appropriation > 0 ? (expenditure / appropriation) * 100 : 0
    return {
      category: cat.name,
      appropriation,
      expenditure,
      balance,
      utilization,
      itemCount: items.length,
    }
  }).filter((d) => d.appropriation > 0)

  const chartData = categoryBreakdown.map((d) => ({
    name: d.category.length > 12 ? d.category.slice(0, 12) + "..." : d.category,
    Appropriation: d.appropriation,
    Expenditure: d.expenditure,
    Balance: d.balance,
  }))

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-semibold text-foreground">Financial Reports</h3>
          <p className="text-sm text-muted-foreground">Budget utilization and financial summary reports</p>
        </div>
        <div className="flex items-center gap-2">
          <Select value={selectedYear} onValueChange={setSelectedYear}>
            <SelectTrigger className="w-48">
              <SelectValue placeholder="Select budget year" />
            </SelectTrigger>
            <SelectContent>
              {budgets.map((b) => (
                <SelectItem key={b.id} value={b.id}>
                  FY {b.year}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button variant="outline" size="sm" onClick={() => window.print()}>
            <Printer className="mr-1.5 h-4 w-4" />
            Print
          </Button>
        </div>
      </div>

      {/* Overview Summary */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Budget Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Appropriation</span>
              <span className="font-mono font-semibold text-foreground">{formatCurrency(totalAppropriation)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Expenditure</span>
              <span className="font-mono font-semibold text-foreground">{formatCurrency(totalExpenditure)}</span>
            </div>
            <div className="border-t border-border pt-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-semibold text-foreground">Balance</span>
                <span className="font-mono font-bold text-foreground">{formatCurrency(totalBalance)}</span>
              </div>
            </div>
            <Progress value={totalAppropriation > 0 ? (totalExpenditure / totalAppropriation) * 100 : 0} className="h-2" />
            <p className="text-center text-xs text-muted-foreground">
              {totalAppropriation > 0 ? ((totalExpenditure / totalAppropriation) * 100).toFixed(1) : "0.0"}% utilized
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Expense Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Amount</span>
              <span className="font-mono font-semibold text-foreground">{formatCurrency(totalExpenseAmount)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Paid</span>
              <span className="font-mono font-semibold text-foreground">{formatCurrency(totalExpensePaid)}</span>
            </div>
            <div className="border-t border-border pt-2">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Outstanding</span>
                <span className="font-mono font-semibold text-foreground">{formatCurrency(totalExpenseAmount - totalExpensePaid)}</span>
              </div>
            </div>
            <div className="flex gap-4 text-xs">
              <span className="text-accent">{approvedExpenses} approved</span>
              <span className="text-chart-3">{pendingExpenses} pending</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Disbursement Summary</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Disbursed</span>
              <span className="font-mono font-semibold text-foreground">{formatCurrency(totalDisbursementAmount)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Total Records</span>
              <span className="font-semibold text-foreground">{disbursements.length}</span>
            </div>
            <div className="border-t border-border pt-2">
              <div className="flex gap-4 text-xs">
                <span className="text-accent">{approvedDisbursements} approved</span>
                <span className="text-chart-3">{pendingDisbursements} pending</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Chart */}
      {budget && (
        <Card>
          <CardHeader className="flex flex-row items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <CardTitle className="text-base">Budget Utilization by Category - FY {budget.year}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ top: 10, right: 10, left: 10, bottom: 10 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(214, 18%, 88%)" />
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 11, fill: "hsl(215, 10%, 45%)" }}
                    angle={-15}
                    textAnchor="end"
                    height={60}
                  />
                  <YAxis
                    tick={{ fontSize: 11, fill: "hsl(215, 10%, 45%)" }}
                    tickFormatter={(v) => `${(v / 1000000).toFixed(1)}M`}
                  />
                  <Tooltip
                    formatter={(value: number) => formatCurrency(value)}
                    contentStyle={{
                      backgroundColor: "hsl(0, 0%, 100%)",
                      border: "1px solid hsl(214, 18%, 88%)",
                      borderRadius: "8px",
                      fontSize: "12px",
                    }}
                  />
                  <Legend />
                  <Bar dataKey="Appropriation" fill="hsl(215, 60%, 32%)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="Expenditure" fill="hsl(160, 50%, 42%)" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="Balance" fill="hsl(35, 85%, 55%)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Detailed Category Breakdown Table */}
      {budget && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Detailed Budget Breakdown - FY {budget.year}</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {categories.map((cat) => {
              const items = budget.items.filter((i) => i.categoryId === cat.id)
              if (items.length === 0) return null
              const subApprop = items.reduce((s, i) => s + i.appropriation, 0)
              const subExpend = items.reduce((s, i) => s + i.expenditure, 0)
              const subBalance = subApprop - subExpend
              const subPct = subApprop > 0 ? ((subBalance / subApprop) * 100).toFixed(0) : "100"
              return (
                <div key={cat.id} className="border-b border-border last:border-b-0">
                  <div className="bg-muted/30 px-4 py-2">
                    <h4 className="font-bold text-foreground">{cat.name}</h4>
                  </div>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="font-semibold">Particular</TableHead>
                        <TableHead className="text-right font-semibold">Appropriation</TableHead>
                        <TableHead className="text-right font-semibold">Expenditure</TableHead>
                        <TableHead className="text-right font-semibold">Balance</TableHead>
                        <TableHead className="text-right font-semibold">%</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {items.map((item) => {
                        const bal = item.appropriation - item.expenditure
                        const pct = item.appropriation > 0 ? ((bal / item.appropriation) * 100).toFixed(0) : "100"
                        return (
                          <TableRow key={item.id}>
                            <TableCell className="text-foreground">
                              {particulars.find((p) => p.id === item.particularId)?.particular || "Unknown"}
                            </TableCell>
                            <TableCell className="text-right font-mono text-foreground">{formatCurrency(item.appropriation)}</TableCell>
                            <TableCell className="text-right font-mono text-foreground">{formatCurrency(item.expenditure)}</TableCell>
                            <TableCell className="text-right font-mono text-foreground">{formatCurrency(bal)}</TableCell>
                            <TableCell className="text-right font-mono text-foreground">{pct}%</TableCell>
                          </TableRow>
                        )
                      })}
                    </TableBody>
                    <TableFooter>
                      <TableRow>
                        <TableCell className="font-semibold text-foreground">SUB TOTAL:</TableCell>
                        <TableCell className="text-right font-mono font-semibold text-foreground">{formatCurrency(subApprop)}</TableCell>
                        <TableCell className="text-right font-mono font-semibold text-foreground">{formatCurrency(subExpend)}</TableCell>
                        <TableCell className="text-right font-mono font-semibold text-foreground">{formatCurrency(subBalance)}</TableCell>
                        <TableCell className="text-right font-mono font-semibold text-foreground">{subPct}%</TableCell>
                      </TableRow>
                    </TableFooter>
                  </Table>
                </div>
              )
            })}
          </CardContent>
        </Card>
      )}

      {/* Grand Totals */}
      {budget && (
        <Card>
          <CardContent className="py-6">
            <div className="flex flex-col items-end gap-2">
              <div className="grid grid-cols-2 gap-x-8 gap-y-1 text-right">
                <span className="text-lg font-bold text-foreground">TOTAL APPROPRIATION:</span>
                <span className="font-mono text-lg font-bold text-foreground">{formatCurrency(totalAppropriation)}</span>
                <span className="text-lg font-bold text-foreground">TOTAL EXPENDITURE:</span>
                <div className="flex items-center justify-end gap-2">
                  <span className="font-mono text-lg font-bold text-foreground">{formatCurrency(totalExpenditure)}</span>
                  <span className="text-sm text-muted-foreground">
                    {totalAppropriation > 0 ? ((totalExpenditure / totalAppropriation) * 100).toFixed(1) : "0.0"}%
                  </span>
                </div>
                <span className="text-lg font-bold text-foreground">BALANCE:</span>
                <div className="flex items-center justify-end gap-2">
                  <span className="font-mono text-lg font-bold text-foreground">{formatCurrency(totalBalance)}</span>
                  <span className="text-sm text-muted-foreground">
                    {totalAppropriation > 0 ? ((totalBalance / totalAppropriation) * 100).toFixed(1) : "100.0"}%
                  </span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
