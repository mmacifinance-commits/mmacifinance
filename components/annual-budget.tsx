"use client"

import { useState } from "react"
import { useBudget } from "@/lib/budget-context"
import { formatCurrency } from "@/lib/store"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
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
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Plus, Eye, Trash2, ArrowLeft, Pencil } from "lucide-react"

export function AnnualBudget() {
  const {
    budgets,
    categories,
    departments,
    particulars,
    addBudget,
    addBudgetItem,
    updateBudgetItem,
    deleteBudgetItem,
    deleteBudget,
  } = useBudget()
  const [viewBudgetId, setViewBudgetId] = useState<string | null>(null)
  const [newYearDialog, setNewYearDialog] = useState(false)
  const [newYear, setNewYear] = useState("")
  const [addItemDialog, setAddItemDialog] = useState(false)
  const [editItemId, setEditItemId] = useState<string | null>(null)
  const [itemCategoryId, setItemCategoryId] = useState("")
  const [itemParticularId, setItemParticularId] = useState("")
  const [itemAppropriation, setItemAppropriation] = useState("")

  const viewBudget = budgets.find((b) => b.id === viewBudgetId)

  function handleNewBudget() {
    const yr = parseInt(newYear)
    if (!yr || yr < 2000 || yr > 2100) return
    if (budgets.some((b) => b.year === yr)) return
    addBudget(yr)
    setNewYearDialog(false)
    setNewYear("")
  }

  function openAddItem() {
    setEditItemId(null)
    setItemCategoryId("")
    setItemParticularId("")
    setItemAppropriation("")
    setAddItemDialog(true)
  }

  function openEditItem(itemId: string) {
    if (!viewBudget) return
    const item = viewBudget.items.find((i) => i.id === itemId)
    if (!item) return
    setEditItemId(itemId)
    setItemCategoryId(item.categoryId)
    setItemParticularId(item.particularId)
    setItemAppropriation(item.appropriation.toString())
    setAddItemDialog(true)
  }

  function handleSaveItem() {
    if (!viewBudgetId || !itemCategoryId || !itemParticularId || !itemAppropriation) return
    if (editItemId) {
      updateBudgetItem(viewBudgetId, editItemId, {
        categoryId: itemCategoryId,
        particularId: itemParticularId,
        appropriation: parseFloat(itemAppropriation),
      })
    } else {
      addBudgetItem(viewBudgetId, {
        categoryId: itemCategoryId,
        particularId: itemParticularId,
        appropriation: parseFloat(itemAppropriation),
        expenditure: 0,
      })
    }
    setAddItemDialog(false)
    setEditItemId(null)
    setItemCategoryId("")
    setItemParticularId("")
    setItemAppropriation("")
  }

  function getParticularName(id: string) {
    return particulars.find((p) => p.id === id)?.particular || "Unknown"
  }

  function getDepartmentByParticular(particularId: string) {
    const par = particulars.find((p) => p.id === particularId)
    if (!par) return "N/A"
    return departments.find((d) => d.id === par.departmentId)?.name || "N/A"
  }

  const filteredParticulars = particulars.filter((p) => p.categoryId === itemCategoryId)

  // List view
  if (!viewBudget) {
    return (
      <div className="space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="text-lg font-bold text-[#1a2744]">Annual Budget</h3>
            <p className="text-sm text-muted-foreground">Manage annual budget allocations</p>
          </div>
          <Button onClick={() => setNewYearDialog(true)} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
            <Plus className="mr-1.5 h-4 w-4" />
            New Annual Budget
          </Button>
        </div>

        <div className="rounded border border-border overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-[#1a2744]">
                <TableHead className="font-semibold text-white">Year</TableHead>
                <TableHead className="text-right font-semibold text-white">Total Expenditure</TableHead>
                <TableHead className="text-right font-semibold text-white">Balance</TableHead>
                <TableHead className="text-right font-semibold text-white">Utilization Rate</TableHead>
                <TableHead className="w-28 text-center font-semibold text-white">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {budgets.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                    No annual budgets created yet
                  </TableCell>
                </TableRow>
              ) : (
                budgets
                  .sort((a, b) => b.year - a.year)
                  .map((budget, idx) => {
                    const totalApp = budget.items.reduce((s, i) => s + i.appropriation, 0)
                    const totalExp = budget.items.reduce((s, i) => s + i.expenditure, 0)
                    const balance = totalApp - totalExp
                    const util = totalApp > 0 ? ((totalExp / totalApp) * 100).toFixed(1) : "0.0"
                    return (
                      <TableRow key={budget.id} className={idx % 2 === 0 ? "bg-card" : "bg-muted/30"}>
                        <TableCell className="font-bold text-[#1a2744]">{budget.year}</TableCell>
                        <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(totalExp)}</TableCell>
                        <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(balance)}</TableCell>
                        <TableCell className="text-right font-mono font-semibold text-[#d4a843]">{util}%</TableCell>
                        <TableCell>
                          <div className="flex items-center justify-center gap-1">
                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setViewBudgetId(budget.id)}>
                              <Eye className="h-3.5 w-3.5" />
                              <span className="sr-only">View</span>
                            </Button>
                            <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:text-destructive" onClick={() => deleteBudget(budget.id)}>
                              <Trash2 className="h-3.5 w-3.5" />
                              <span className="sr-only">Delete</span>
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })
              )}
            </TableBody>
          </Table>
          <div className="border-t border-border bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
            Total Records: {budgets.length}
          </div>
        </div>

        <Dialog open={newYearDialog} onOpenChange={setNewYearDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-[#1a2744]">New Annual Budget</DialogTitle>
              <DialogDescription>Create a new annual budget for a fiscal year.</DialogDescription>
            </DialogHeader>
            <div className="space-y-2 py-2">
              <Label htmlFor="new-year">Fiscal Year</Label>
              <Input id="new-year" type="number" value={newYear} onChange={(e) => setNewYear(e.target.value)} placeholder="e.g. 2026" />
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setNewYearDialog(false)}>Cancel</Button>
              <Button onClick={handleNewBudget} className="bg-[#1a2744] text-white hover:bg-[#243352]">Create</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    )
  }

  // Detail View
  const groupedByCategory = categories
    .map((cat) => {
      const items = viewBudget.items.filter((i) => i.categoryId === cat.id)
      if (items.length === 0) return null
      const subTotal = items.reduce((s, i) => s + i.appropriation, 0)
      const subExpend = items.reduce((s, i) => s + i.expenditure, 0)
      return { category: cat, items, subTotal, subExpend }
    })
    .filter(Boolean)

  const grandAppropriation = viewBudget.items.reduce((s, i) => s + i.appropriation, 0)
  const grandExpenditure = viewBudget.items.reduce((s, i) => s + i.expenditure, 0)
  const grandBalance = grandAppropriation - grandExpenditure

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="outline" size="icon" className="h-8 w-8 border-[#1a2744] text-[#1a2744]" onClick={() => setViewBudgetId(null)}>
          <ArrowLeft className="h-4 w-4" />
          <span className="sr-only">Back</span>
        </Button>
        <div>
          <h3 className="text-lg font-bold text-[#1a2744]">View Annual Budget</h3>
          <p className="text-sm text-muted-foreground">Fiscal Year {viewBudget.year}</p>
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Button onClick={openAddItem} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
          <Plus className="mr-1.5 h-4 w-4" />
          Add Budget Item
        </Button>
      </div>

      {/* Budget breakdown by category */}
      <div className="space-y-6">
        {groupedByCategory.map((group) => {
          if (!group) return null
          const { category, items, subTotal, subExpend } = group
          const subBalance = subTotal - subExpend
          const subUtilPercent = subTotal > 0 ? ((subExpend / subTotal) * 100).toFixed(0) : "0"
          return (
            <Card key={category.id} className="overflow-hidden">
              <CardHeader className="bg-[#243352] py-3">
                <CardTitle className="text-base font-bold text-white">{category.name}</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-[#d4a843]/10">
                      <TableHead className="font-semibold text-[#1a2744]">Responsibility Center</TableHead>
                      <TableHead className="font-semibold text-[#1a2744]">Particular</TableHead>
                      <TableHead className="text-right font-semibold text-[#1a2744]">Appropriation</TableHead>
                      <TableHead className="text-right font-semibold text-[#1a2744]">Expenditure</TableHead>
                      <TableHead className="text-right font-semibold text-[#1a2744]">Balance</TableHead>
                      <TableHead className="text-right font-semibold text-[#1a2744]">%</TableHead>
                      <TableHead className="w-20 text-center font-semibold text-[#1a2744]">
                        <span className="sr-only">Actions</span>
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {items.map((item, idx) => {
                      const balance = item.appropriation - item.expenditure
                      const pct = item.appropriation > 0 ? ((balance / item.appropriation) * 100).toFixed(0) : "100"
                      return (
                        <TableRow key={item.id} className={idx % 2 === 0 ? "" : "bg-muted/20"}>
                          <TableCell className="text-sm text-muted-foreground">{getDepartmentByParticular(item.particularId)}</TableCell>
                          <TableCell className="text-[#1a2744]">{getParticularName(item.particularId)}</TableCell>
                          <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(item.appropriation)}</TableCell>
                          <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(item.expenditure)}</TableCell>
                          <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(balance)}</TableCell>
                          <TableCell className="text-right font-mono font-semibold text-[#d4a843]">{pct}%</TableCell>
                          <TableCell className="text-center">
                            <div className="flex items-center justify-center gap-0.5">
                              <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEditItem(item.id)}>
                                <Pencil className="h-3 w-3" />
                                <span className="sr-only">Edit item</span>
                              </Button>
                              <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => deleteBudgetItem(viewBudget.id, item.id)}>
                                <Trash2 className="h-3 w-3" />
                                <span className="sr-only">Remove item</span>
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      )
                    })}
                  </TableBody>
                  <TableFooter>
                    <TableRow className="bg-[#d4a843]/10">
                      <TableCell colSpan={2} className="font-bold text-[#1a2744]">SUB TOTAL:</TableCell>
                      <TableCell className="text-right font-mono font-bold text-[#1a2744]">{formatCurrency(subTotal)}</TableCell>
                      <TableCell className="text-right font-mono font-bold text-[#1a2744]">{formatCurrency(subExpend)}</TableCell>
                      <TableCell className="text-right font-mono font-bold text-[#1a2744]">{formatCurrency(subBalance)}</TableCell>
                      <TableCell className="text-right font-mono font-bold text-[#d4a843]">{subUtilPercent}%</TableCell>
                      <TableCell />
                    </TableRow>
                  </TableFooter>
                </Table>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Grand Totals */}
      <Card className="border-2 border-[#1a2744]">
        <CardContent className="py-6">
          <div className="flex flex-col items-end gap-2">
            <div className="grid grid-cols-3 gap-x-6 gap-y-2 text-right">
              <span className="col-span-1 text-lg font-bold text-[#1a2744]">TOTAL APPROPRIATION:</span>
              <span className="font-mono text-lg font-bold text-[#1a2744]">{formatCurrency(grandAppropriation)}</span>
              <span />
              <span className="col-span-1 text-lg font-bold text-[#1a2744]">TOTAL EXPENDITURE:</span>
              <span className="font-mono text-lg font-bold text-[#1a2744]">{formatCurrency(grandExpenditure)}</span>
              <span className="text-lg font-bold text-[#d4a843]">
                {grandAppropriation > 0 ? ((grandExpenditure / grandAppropriation) * 100).toFixed(1) : "0.0"}%
              </span>
              <span className="col-span-1 text-lg font-bold text-[#1a2744]">BALANCE:</span>
              <span className="font-mono text-lg font-bold text-[#1a2744]">{formatCurrency(grandBalance)}</span>
              <span className="text-lg font-bold text-[#d4a843]">
                {grandAppropriation > 0 ? ((grandBalance / grandAppropriation) * 100).toFixed(1) : "100.0"}%
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={addItemDialog} onOpenChange={setAddItemDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="text-[#1a2744]">{editItemId ? "Edit Budget Item" : "Add Budget Item"}</DialogTitle>
            <DialogDescription>{editItemId ? "Update the appropriation line item." : "Add a new appropriation line item."}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Category</Label>
              <Select value={itemCategoryId} onValueChange={(v) => { setItemCategoryId(v); setItemParticularId("") }}>
                <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
                <SelectContent>
                  {categories.map((cat) => (
                    <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Particular</Label>
              <Select value={itemParticularId} onValueChange={setItemParticularId}>
                <SelectTrigger><SelectValue placeholder="Select particular" /></SelectTrigger>
                <SelectContent>
                  {filteredParticulars.map((par) => (
                    <SelectItem key={par.id} value={par.id}>
                      {par.particular} ({par.accountCode})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="item-amount">Appropriation Amount</Label>
              <Input id="item-amount" type="number" value={itemAppropriation} onChange={(e) => setItemAppropriation(e.target.value)} placeholder="0.00" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddItemDialog(false)}>Cancel</Button>
            <Button onClick={handleSaveItem} className="bg-[#1a2744] text-white hover:bg-[#243352]">{editItemId ? "Update" : "Add Item"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
