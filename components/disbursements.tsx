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
import { Plus, Pencil, Trash2, CheckCircle, Clock, XCircle, FileCheck } from "lucide-react"

function DisbursementStatusBadge({ status }: { status: string }) {
  if (status === "posted") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
        <FileCheck className="h-3 w-3" /> Posted
      </span>
    )
  }
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

const methodLabels: Record<string, string> = {
  check: "Check",
  cash: "Cash",
  bank_transfer: "Bank Transfer",
}

export function Disbursements() {
  const { disbursements, addDisbursement, updateDisbursement, deleteDisbursement } = useBudget()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [description, setDescription] = useState("")
  const [source, setSource] = useState("")
  const [payTo, setPayTo] = useState("")
  const [amount, setAmount] = useState("")
  const [method, setMethod] = useState<"check" | "cash" | "bank_transfer">("check")
  const [notes, setNotes] = useState("")
  const [filterStatus, setFilterStatus] = useState("all")
  const [searchQuery, setSearchQuery] = useState("")

  const filtered = disbursements.filter((d) => {
    const matchStatus = filterStatus === "all" || d.status === filterStatus
    const matchSearch =
      d.disbursementNo.toLowerCase().includes(searchQuery.toLowerCase()) ||
      d.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
      d.payTo.toLowerCase().includes(searchQuery.toLowerCase())
    return matchStatus && matchSearch
  })

  function openNew() {
    setEditId(null)
    setDescription("")
    setSource("")
    setPayTo("")
    setAmount("")
    setMethod("check")
    setNotes("")
    setDialogOpen(true)
  }

  function openEdit(id: string) {
    const dsb = disbursements.find((d) => d.id === id)
    if (!dsb) return
    setEditId(id)
    setDescription(dsb.description)
    setSource(dsb.source)
    setPayTo(dsb.payTo)
    setAmount(dsb.amount.toString())
    setMethod(dsb.method)
    setNotes(dsb.notes)
    setDialogOpen(true)
  }

  function handleSave() {
    if (!description.trim() || !amount || !payTo.trim()) return
    const data = {
      description: description.trim(),
      source: source.trim() || "Expenditure",
      payTo: payTo.trim(),
      amount: parseFloat(amount),
      method,
      notes: notes.trim(),
      dateEncoded: new Date().toISOString().split("T")[0],
      dateApproved: null,
      status: "pending" as const,
    }
    if (editId) {
      updateDisbursement(editId, data)
    } else {
      addDisbursement(data)
    }
    setDialogOpen(false)
  }

  function handleApprove(id: string) {
    updateDisbursement(id, { status: "approved", dateApproved: new Date().toISOString().split("T")[0] })
  }

  function handlePost(id: string) {
    updateDisbursement(id, { status: "posted" })
  }

  function handleCancel(id: string) {
    updateDisbursement(id, { status: "cancelled" })
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-bold text-[#1a2744]">Disbursements</h3>
          <p className="text-sm text-muted-foreground">Track fund disbursement transactions</p>
        </div>
        <Button onClick={openNew} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
          <Plus className="mr-1.5 h-4 w-4" />
          New Disbursement
        </Button>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <Input
          placeholder="Search disbursements..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-72"
        />
        <Select value={filterStatus} onValueChange={setFilterStatus}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="approved">Approved</SelectItem>
            <SelectItem value="posted">Posted</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="rounded border border-border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-[#1a2744]">
              <TableHead className="font-semibold text-white">Disbursement No.</TableHead>
              <TableHead className="font-semibold text-white">Description</TableHead>
              <TableHead className="font-semibold text-white">Source</TableHead>
              <TableHead className="font-semibold text-white">Pay To</TableHead>
              <TableHead className="text-right font-semibold text-white">Amount</TableHead>
              <TableHead className="font-semibold text-white">Method</TableHead>
              <TableHead className="font-semibold text-white">Date Encoded</TableHead>
              <TableHead className="font-semibold text-white">Date Approved</TableHead>
              <TableHead className="font-semibold text-white">Status</TableHead>
              <TableHead className="w-32 text-center font-semibold text-white">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.length === 0 ? (
              <TableRow>
                <TableCell colSpan={10} className="py-8 text-center text-muted-foreground">
                  No disbursement records found
                </TableCell>
              </TableRow>
            ) : (
              filtered.map((dsb, idx) => (
                <TableRow key={dsb.id} className={idx % 2 === 0 ? "bg-card" : "bg-muted/30"}>
                  <TableCell className="font-mono text-sm text-[#1a2744]">{dsb.disbursementNo}</TableCell>
                  <TableCell className="text-[#1a2744]">{dsb.description}</TableCell>
                  <TableCell className="text-muted-foreground">{dsb.source}</TableCell>
                  <TableCell className="text-[#1a2744]">{dsb.payTo}</TableCell>
                  <TableCell className="text-right font-mono text-[#1a2744]">{formatCurrency(dsb.amount)}</TableCell>
                  <TableCell className="text-muted-foreground">{methodLabels[dsb.method]}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{dsb.dateEncoded}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{dsb.dateApproved || "-"}</TableCell>
                  <TableCell><DisbursementStatusBadge status={dsb.status} /></TableCell>
                  <TableCell>
                    <div className="flex items-center justify-center gap-0.5">
                      {dsb.status === "pending" && (
                        <>
                          <Button variant="ghost" size="icon" className="h-7 w-7 text-green-600 hover:text-green-700" onClick={() => handleApprove(dsb.id)} title="Approve">
                            <CheckCircle className="h-3.5 w-3.5" />
                            <span className="sr-only">Approve</span>
                          </Button>
                          <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500 hover:text-red-600" onClick={() => handleCancel(dsb.id)} title="Cancel">
                            <XCircle className="h-3.5 w-3.5" />
                            <span className="sr-only">Cancel</span>
                          </Button>
                        </>
                      )}
                      {dsb.status === "approved" && (
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-blue-600 hover:text-blue-700" onClick={() => handlePost(dsb.id)} title="Post">
                          <FileCheck className="h-3.5 w-3.5" />
                          <span className="sr-only">Post</span>
                        </Button>
                      )}
                      <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(dsb.id)}>
                        <Pencil className="h-3.5 w-3.5" />
                        <span className="sr-only">Edit</span>
                      </Button>
                      <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => deleteDisbursement(dsb.id)}>
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
            <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => { setFilterStatus("all"); setSearchQuery("") }}>Clear Filters</Button>
          </div>
        </div>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-[#1a2744]">{editId ? "Edit Disbursement" : "New Disbursement"}</DialogTitle>
            <DialogDescription>{editId ? "Update the disbursement record." : "Record a new fund disbursement."}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="dsb-desc">Description</Label>
              <Input id="dsb-desc" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="e.g. Computer Equipment" />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="dsb-source">Source</Label>
                <Input id="dsb-source" value={source} onChange={(e) => setSource(e.target.value)} placeholder="e.g. Expenditure" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="dsb-payto">Pay To</Label>
                <Input id="dsb-payto" value={payTo} onChange={(e) => setPayTo(e.target.value)} placeholder="e.g. Vendor Name" />
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="dsb-amount">Amount</Label>
                <Input id="dsb-amount" type="number" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0.00" />
              </div>
              <div className="space-y-2">
                <Label>Payment Method</Label>
                <Select value={method} onValueChange={(v) => setMethod(v as typeof method)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="check">Check</SelectItem>
                    <SelectItem value="cash">Cash</SelectItem>
                    <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="dsb-notes">Notes</Label>
              <Textarea id="dsb-notes" value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Additional notes..." rows={3} />
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
