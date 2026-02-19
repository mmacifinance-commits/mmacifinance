"use client"

import { useState } from "react"
import { BudgetCategories } from "./budget-categories"
import { BudgetParticulars } from "./budget-particulars"
import { AnnualBudget } from "./annual-budget"
import { cn } from "@/lib/utils"
import { FolderOpen, Tag, FileSpreadsheet } from "lucide-react"

const subMenuItems = [
  { key: "annual", label: "Annual Budget", icon: FileSpreadsheet },
  { key: "categories", label: "Budget Categories", icon: Tag },
  { key: "particulars", label: "Budget Particulars", icon: FolderOpen },
]

export function BudgetModule() {
  const [activeSubMenu, setActiveSubMenu] = useState("annual")

  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:gap-6">
      {/* Sub Menu Sidebar */}
      <aside className="w-full shrink-0 lg:w-56">
        <div className="rounded border border-border bg-card overflow-hidden">
          <div className="bg-[#1a2744] px-4 py-2.5">
            <h3 className="text-sm font-bold uppercase tracking-wide text-white">Sub Menu</h3>
          </div>
          <nav className="p-2">
            {subMenuItems.map((item) => {
              const Icon = item.icon
              return (
                <button
                  key={item.key}
                  onClick={() => setActiveSubMenu(item.key)}
                  className={cn(
                    "flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm transition-colors",
                    activeSubMenu === item.key
                      ? "bg-[#d4a843]/15 font-semibold text-[#1a2744]"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground"
                  )}
                >
                  <Icon className={cn("h-4 w-4", activeSubMenu === item.key ? "text-[#d4a843]" : "")} />
                  {item.label}
                </button>
              )
            })}
          </nav>
        </div>
      </aside>

      {/* Content */}
      <div className="flex-1 min-w-0">
        {activeSubMenu === "annual" && <AnnualBudget />}
        {activeSubMenu === "categories" && <BudgetCategories />}
        {activeSubMenu === "particulars" && <BudgetParticulars />}
      </div>
    </div>
  )
}
