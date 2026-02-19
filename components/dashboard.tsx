"use client"

import { useBudget } from "@/lib/budget-context"
import { formatCurrency } from "@/lib/store"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  Wallet,
  TrendingDown,
  PiggyBank,
  FileText,
  CreditCard,
  BarChart3,
} from "lucide-react"
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from "recharts"
import Image from "next/image"

const COLORS = ["#1a2744", "#d4a843", "#2c5282", "#8b6914", "#4a6fa5"]

export function Dashboard() {
  const { budgets, categories, expenses, disbursements } = useBudget()

  const currentBudget = budgets.find((b) => b.year === 2025)
  const totalAppropriation = currentBudget
    ? currentBudget.items.reduce((sum, item) => sum + item.appropriation, 0)
    : 0
  const totalExpenditure = currentBudget
    ? currentBudget.items.reduce((sum, item) => sum + item.expenditure, 0)
    : 0
  const totalBalance = totalAppropriation - totalExpenditure
  const utilizationRate = totalAppropriation > 0 ? (totalExpenditure / totalAppropriation) * 100 : 0

  const pendingExpenses = expenses.filter((e) => e.status === "pending").length
  const pendingDisbursements = disbursements.filter((d) => d.status === "pending").length

  const categoryData = categories.map((cat) => {
    const catItems = currentBudget?.items.filter((i) => i.categoryId === cat.id) || []
    const appropriation = catItems.reduce((s, i) => s + i.appropriation, 0)
    const expenditure = catItems.reduce((s, i) => s + i.expenditure, 0)
    return {
      name: cat.name.length > 15 ? cat.name.slice(0, 15) + "..." : cat.name,
      fullName: cat.name,
      Appropriation: appropriation,
      Expenditure: expenditure,
    }
  }).filter((d) => d.Appropriation > 0)

  const pieData = categories.map((cat) => {
    const catItems = currentBudget?.items.filter((i) => i.categoryId === cat.id) || []
    const value = catItems.reduce((s, i) => s + i.appropriation, 0)
    return { name: cat.name, value }
  }).filter((d) => d.value > 0)

  return (
    <div className="space-y-6">
      {/* Hero Section with Logo */}
      <div className="flex items-center gap-4">
        <Image
          src="/images/logo.jpg"
          alt="MMACI Logo"
          width={64}
          height={64}
          className="rounded-full border-2 border-[#d4a843] shadow-lg"
        />
        <div>
          <h2 className="text-2xl font-bold text-[#1a2744]">Dashboard</h2>
          <p className="text-sm text-muted-foreground">
            Budget & Funds Utilization Overview - FY 2025-2026
          </p>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="border-l-4 border-l-[#1a2744]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Appropriation
            </CardTitle>
            <Wallet className="h-4 w-4 text-[#1a2744]" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-[#1a2744]">
              {formatCurrency(totalAppropriation)}
            </div>
            <p className="text-xs text-muted-foreground">FY 2025 Budget</p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-[#d4a843]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Expenditure
            </CardTitle>
            <TrendingDown className="h-4 w-4 text-[#d4a843]" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-[#1a2744]">
              {formatCurrency(totalExpenditure)}
            </div>
            <p className="text-xs text-[#d4a843] font-semibold">
              {utilizationRate.toFixed(1)}% utilized
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-[#2c5282]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Remaining Balance
            </CardTitle>
            <PiggyBank className="h-4 w-4 text-[#2c5282]" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-[#1a2744]">
              {formatCurrency(totalBalance)}
            </div>
            <p className="text-xs text-muted-foreground">
              {(100 - utilizationRate).toFixed(1)}% remaining
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-red-500">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Pending Approvals
            </CardTitle>
            <FileText className="h-4 w-4 text-red-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-[#1a2744]">
              {pendingExpenses + pendingDisbursements}
            </div>
            <p className="text-xs text-muted-foreground">
              {pendingExpenses} expenses, {pendingDisbursements} disbursements
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Utilization Progress */}
      <Card>
        <CardHeader className="bg-[#1a2744] rounded-t-lg">
          <CardTitle className="text-base text-white">Budget Utilization Rate</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 pt-4">
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">Overall Utilization</span>
            <span className="font-semibold text-[#1a2744]">{utilizationRate.toFixed(1)}%</span>
          </div>
          <Progress value={utilizationRate} className="h-3" />
          <div className="grid gap-3 pt-2 md:grid-cols-2 lg:grid-cols-3">
            {categories.map((cat) => {
              const items = currentBudget?.items.filter((i) => i.categoryId === cat.id) || []
              const approp = items.reduce((s, i) => s + i.appropriation, 0)
              const expend = items.reduce((s, i) => s + i.expenditure, 0)
              const rate = approp > 0 ? (expend / approp) * 100 : 0
              if (approp === 0) return null
              return (
                <div key={cat.id} className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <span className="truncate text-muted-foreground">{cat.name}</span>
                    <span className="font-medium text-[#1a2744]">{rate.toFixed(1)}%</span>
                  </div>
                  <Progress value={rate} className="h-2" />
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Charts */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader className="flex flex-row items-center gap-2 bg-[#1a2744] rounded-t-lg">
            <BarChart3 className="h-5 w-5 text-[#d4a843]" />
            <CardTitle className="text-base text-white">Appropriation vs Expenditure</CardTitle>
          </CardHeader>
          <CardContent className="pt-4">
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={categoryData} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 10, fill: "#64748b" }}
                    angle={-20}
                    textAnchor="end"
                    height={60}
                  />
                  <YAxis
                    tick={{ fontSize: 10, fill: "#64748b" }}
                    tickFormatter={(v) => `${(v / 1000000).toFixed(1)}M`}
                  />
                  <Tooltip
                    formatter={(value: number) => formatCurrency(value)}
                    contentStyle={{
                      backgroundColor: "#fff",
                      border: "1px solid #e2e8f0",
                      borderRadius: "6px",
                      fontSize: "12px",
                    }}
                  />
                  <Bar dataKey="Appropriation" fill="#1a2744" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="Expenditure" fill="#d4a843" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center gap-2 bg-[#1a2744] rounded-t-lg">
            <CreditCard className="h-5 w-5 text-[#d4a843]" />
            <CardTitle className="text-base text-white">Budget Allocation by Category</CardTitle>
          </CardHeader>
          <CardContent className="pt-4">
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={pieData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={100}
                    paddingAngle={3}
                    dataKey="value"
                  >
                    {pieData.map((_, idx) => (
                      <Cell key={idx} fill={COLORS[idx % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip
                    formatter={(value: number) => formatCurrency(value)}
                    contentStyle={{
                      backgroundColor: "#fff",
                      border: "1px solid #e2e8f0",
                      borderRadius: "6px",
                      fontSize: "12px",
                    }}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="mt-2 flex flex-wrap gap-3">
              {pieData.map((entry, idx) => (
                <div key={entry.name} className="flex items-center gap-1.5 text-xs">
                  <div
                    className="h-2.5 w-2.5 rounded-full"
                    style={{ backgroundColor: COLORS[idx % COLORS.length] }}
                  />
                  <span className="text-muted-foreground">{entry.name}</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader className="bg-[#1a2744] rounded-t-lg">
            <CardTitle className="text-base text-white">Recent Expenses</CardTitle>
          </CardHeader>
          <CardContent className="pt-4">
            <div className="space-y-3">
              {expenses.slice(0, 4).map((exp) => (
                <div key={exp.id} className="flex items-center justify-between rounded border border-border p-3">
                  <div>
                    <p className="text-sm font-medium text-[#1a2744]">{exp.refNo}</p>
                    <p className="text-xs text-muted-foreground">{exp.description}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-[#1a2744]">{formatCurrency(exp.amount)}</p>
                    <span
                      className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                        exp.status === "approved"
                          ? "bg-green-100 text-green-700"
                          : exp.status === "pending"
                          ? "bg-[#d4a843]/15 text-[#8b6914]"
                          : "bg-red-100 text-red-700"
                      }`}
                    >
                      {exp.status.charAt(0).toUpperCase() + exp.status.slice(1)}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="bg-[#1a2744] rounded-t-lg">
            <CardTitle className="text-base text-white">Recent Disbursements</CardTitle>
          </CardHeader>
          <CardContent className="pt-4">
            <div className="space-y-3">
              {disbursements.slice(0, 4).map((dsb) => (
                <div key={dsb.id} className="flex items-center justify-between rounded border border-border p-3">
                  <div>
                    <p className="text-sm font-medium text-[#1a2744]">{dsb.disbursementNo}</p>
                    <p className="text-xs text-muted-foreground">{dsb.description} - {dsb.payTo}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-[#1a2744]">{formatCurrency(dsb.amount)}</p>
                    <span
                      className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                        dsb.status === "posted"
                          ? "bg-blue-100 text-blue-700"
                          : dsb.status === "approved"
                          ? "bg-green-100 text-green-700"
                          : dsb.status === "pending"
                          ? "bg-[#d4a843]/15 text-[#8b6914]"
                          : "bg-red-100 text-red-700"
                      }`}
                    >
                      {dsb.status.charAt(0).toUpperCase() + dsb.status.slice(1)}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
