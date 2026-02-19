"use client"

import { useState } from "react"
import { useBudget } from "@/lib/budget-context"
import { formatCurrency } from "@/lib/store"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
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
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog"
import { Plus, Pencil, Trash2, CheckCircle, Clock, XCircle } from "lucide-react"

function StatusBadge({ status }: { status: string }) {
  if (status === "approved") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
        <CheckCircle className="h-3 w-3" /> Approved
      </span>
    )
  }
  if (status === "pending") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-[#d4a843]/15 px-2.5 py-0.5 text-xs font-semibold text-[#8b6914]">
        <Clock className="h-3 w-3" /> Pending
      </span>
    )
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
      <XCircle className="h-3 w-3" /> Cancelled
    </span>
  )
}

export function Expenditures() {
  const { categories, particulars, expenses, departments, addExpense, updateExpense, deleteExpense } = useBudget()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [description, setDescription] = useState("")
  const [categoryId, setCategoryId] = useState("")
  const [particularId, setParticularId] = useState("")
  const [amount, setAmount] = useState("")
  const [paid, setPaid] = useState("")
  const [notes, setNotes] = useState("")
  const [filterStatus, setFilterStatus] = useState("all")
  const [filterCategory, setFilterCategory] = useState("all")
  const [searchQuery, setSearchQuery] = useState("")

  const filtered = expenses.filter((e) => {
    const matchStatus = filterStatus === "all" || e.status === filterStatus
    const matchCat = filterCategory === "all" || e.categoryId === filterCategory
    const matchSearch =
      e.refNo.toLowerCase().includes(searchQuery.toLowerCase()) ||
      e.description.toLowerCase().includes(searchQuery.toLowerCase())
    return matchStatus && matchCat && matchSearch
  })

  const filteredParticulars = particulars.filter((p) => p.categoryId === categoryId)

  function getCategoryName(id: string) {
    return categories.find((c) => c.id === id)?.name || "Unknown"
  }

  function getParticularName(id: string) {
    return particulars.find((p) => p.id === id)?.particular || "Unknown"
  }

  function getDepartmentByParticular(particularId: string) {
    const par = particulars.find((p) => p.id === particularId)
    if (!par) return "N/A"
    return departments.find((d) => d.id === par.departmentId)?.name || "N/A"
  }

  function openNew() {
    setEditId(null)
    setDescription("")
    setCategoryId("")
    setParticularId("")
    setAmount("")
    setPaid("")
    setNotes("")
    setDialogOpen(true)
  }

  function openEdit(id: string) {
    const exp = expenses.find((e) => e.id === id)
    if (!exp) return
    setEditId(id)
    setDescription(exp.description)
    setCategoryId(exp.categoryId)
    setParticularId(exp.particularId)
    setAmount(exp.amount.toString())
    setPaid(exp.paid.toString())
    setNotes(exp.notes)
    setDialogOpen(true)
  }

  function handleSave() {
    if (!categoryId || !particularId || !amount) return
    const data = {
      description: description.trim() || getCategoryName(categoryId),
      categoryId,
      particularId,
      amount: parseFloat(amount),
      paid: parseFloat(paid) || 0,
      notes: notes.trim(),
      dateEncoded: new Date().toISOString().split("T")[0],
      dateApproved: null,
      status: "pending" as const,
    }
    if (editId) {
      updateExpense(editId, data)
    } else {
      addExpense(data)
    }
    setDialogOpen(false)
  }

  function handleApprove(id: string) {
    updateExpense(id, { status: "approved", dateApproved: new Date().toISOString().split("T")[0] })
  }

  function handleCancel(id: string) {
    updateExpense(id, { status: "cancelled" })
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-bold text-[#1a2744]">Expenses</h3>
          <p className="text-sm text-muted-foreground">Track and manage expenditure records</p>
        </div>
        <Button onClick={openNew} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
          <Plus className="mr-1.5 h-4 w-4" />
          New Expense
        </Button>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <Input
          placeholder="Search by ref no or description..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-72"
        />
        <Select value={filterCategory} onValueChange={setFilterCategory}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Categories</SelectItem>
            {categories.map((cat) => (
              <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={filterStatus} onValueChange={setFilterStatus}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="approved">Approved</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="rounded border border-border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-[#1a2744]">
              <TableHead className="font-semibold text-white">Ref. No.</TableHead>
              <TableHead className="font-semibold text-white">Description</TableHead>
              <TableHead className="font-semibold text-white">Category</TableHead>
              <TableHead className="font-semibold text-white">Responsibility Center</TableHead>
              <TableHead className="font-semibold text-white">Particular</TableHead>
              <TableHead className="text-right font-semibold text-white">Amount</TableHead>
              <TableHead className="text-right font-semibold text-white">Paid</TableHead>
              <TableHead className="text-right font-semibold text-white">Balance</TableHead>
              <TableHead className="font-semibold text-white">Date Encoded</TableHead>
              <TableHead className="font-semibold text-white">Date Approved</TableHead>
              <TableHead className="font-semibold text-white">Status</TableHead>
              <TableHead className="w-28 text-center font-semibold text-white">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.length === 0 ? (
              <TableRow>
                <TableCell colSpan={11} className="py-8 text-center text-muted-foreground">
                  No expense records found
                </TableCell>
              </TableRow>
            ) : (
              filtered.map((exp, idx) => (
                <TableRow key={exp.id} className={idx % 2 === 0 ? "bg-card" : "bg-muted/30"}>
                  <TableCell className="font-mono text-sm text-[#1a2744]">{exp.refNo}</TableCell>
                  <TableCell className="text-[#1a2744]">{exp.description}</TableCell>
                  <TableCell className="text-muted-foreground">{getCategoryName(exp.categoryId)}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{getDepartmentByParticular(exp.particularId)}</TableCell>
                  <TableCell className="text-muted-foreground">{getParticularName(exp.particularId)}</TableCell>
                  <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(exp.amount)}</TableCell>
                  <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(exp.paid)}</TableCell>
                  <TableCell className="text-right font-mono font-semibold text-[#1a2744]">{formatCurrency(exp.amount - exp.paid)}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{exp.dateEncoded}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{exp.dateApproved || "-"}</TableCell>
                  <TableCell><StatusBadge status={exp.status} /></TableCell>
                  <TableCell>
                    <div className="flex items-center justify-center gap-0.5">
                      {exp.status === "pending" && (
                        <>
                          <Button variant="ghost" size="icon" className="h-7 w-7 text-green-600 hover:text-green-700" onClick={() => handleApprove(exp.id)} title="Approve">
                            <CheckCircle className="h-3.5 w-3.5" />
                            <span className="sr-only">Approve</span>
                          </Button>
                          <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500 hover:text-red-600" onClick={() => handleCancel(exp.id)} title="Cancel">
                            <XCircle className="h-3.5 w-3.5" />
                            <span className="sr-only">Cancel</span>
                          </Button>
                        </>
                      )}
                      <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(exp.id)}>
                        <Pencil className="h-3.5 w-3.5" />
                        <span className="sr-only">Edit</span>
                      </Button>
                      <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => deleteExpense(exp.id)}>
                        <Trash2 className="h-3.5 w-3.5" />
                        <span className="sr-only">Delete</span>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
        <div className="flex items-center justify-between border-t border-border bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
          <span>Total Records: {filtered.length}</span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" className="h-7 text-xs">Apply Filters</Button>
            <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => { setFilterStatus("all"); setFilterCategory("all"); setSearchQuery("") }}>Clear Filters</Button>
          </div>
        </div>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-[#1a2744]">{editId ? "Edit Expense" : "New Expense"}</DialogTitle>
            <DialogDescription>{editId ? "Update the expense record." : "Record a new expenditure transaction."}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Category</Label>
              <Select value={categoryId} onValueChange={(v) => { setCategoryId(v); setParticularId("") }}>
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
              <Select value={particularId} onValueChange={setParticularId}>
                <SelectTrigger><SelectValue placeholder="Select particular" /></SelectTrigger>
                <SelectContent>
                  {filteredParticulars.map((par) => (
                    <SelectItem key={par.id} value={par.id}>{par.particular}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="exp-desc">Description</Label>
              <Input id="exp-desc" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Expense description" />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="exp-amount">Amount</Label>
                <Input id="exp-amount" type="number" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0.00" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="exp-paid">Paid</Label>
                <Input id="exp-paid" type="number" value={paid} onChange={(e) => setPaid(e.target.value)} placeholder="0.00" />
              </div>
            </div>
            {amount && paid && (
              <div className="rounded bg-[#d4a843]/10 px-3 py-2 text-sm">
                <span className="font-semibold text-[#1a2744]">Balance: </span>
                <span className="font-mono font-bold text-[#1a2744]">{formatCurrency((parseFloat(amount) || 0) - (parseFloat(paid) || 0))}</span>
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="exp-notes">Notes</Label>
              <Textarea id="exp-notes" value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Additional notes..." rows={3} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSave} className="bg-[#1a2744] text-white hover:bg-[#243352]">{editId ? "Update" : "Create"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
