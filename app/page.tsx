"use client"

import { useState } from "react"
import { BudgetProvider } from "@/lib/budget-context"
import { AppHeader } from "@/components/app-header"
import { Dashboard } from "@/components/dashboard"
import { BudgetModule } from "@/components/budget-module"
import { Expenditures } from "@/components/expenditures"
import { Disbursements } from "@/components/disbursements"
import { FinancialReports } from "@/components/financial-reports"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  Landmark,
  Receipt,
  CreditCard,
  FileBarChart,
} from "lucide-react"

const mainTabs = [
  { key: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { key: "budget", label: "Budget", icon: Landmark },
  { key: "expenditures", label: "Expenditures", icon: Receipt },
  { key: "disbursements", label: "Disbursements", icon: CreditCard },
  { key: "reports", label: "Financial Reports", icon: FileBarChart },
]

function AppContent() {
  const [activeTab, setActiveTab] = useState("dashboard")

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <AppHeader />

      {/* Main Navigation Tabs - navy blue bar matching the original system */}
      <nav className="border-b-2 border-[#d4a843] bg-[#243352]">
        <div className="flex overflow-x-auto">
          {mainTabs.map((tab) => {
            const Icon = tab.icon
            const isActive = activeTab === tab.key
            return (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={cn(
                  "flex shrink-0 items-center gap-2 px-5 py-2.5 text-sm font-semibold uppercase tracking-wide transition-colors",
                  isActive
                    ? "bg-[#d4a843] text-[#1a2744]"
                    : "text-white/80 hover:bg-[#2c3e5e] hover:text-white"
                )}
              >
                <Icon className="h-4 w-4" />
                {tab.label}
              </button>
            )
          })}
        </div>
      </nav>

      {/* Content */}
      <main className="flex-1 p-4 md:p-6">
        {activeTab === "dashboard" && <Dashboard />}
        {activeTab === "budget" && <BudgetModule />}
        {activeTab === "expenditures" && <Expenditures />}
        {activeTab === "disbursements" && <Disbursements />}
        {activeTab === "reports" && <FinancialReports />}
      </main>

      {/* Footer */}
      <footer className="border-t border-border bg-[#1a2744] px-6 py-3 text-center text-xs text-[#d4a843]/70">
        Merchant Marine Academy of Caraga, Inc. &copy; {new Date().getFullYear()}. All rights reserved.
      </footer>
    </div>
  )
}

export default function Page() {
  return (
    <BudgetProvider>
      <AppContent />
    </BudgetProvider>
  )
}
