"use client"

import { useState } from "react"
import { useBudget } from "@/lib/budget-context"
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
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog"
import { Plus, Pencil, Trash2 } from "lucide-react"

export function BudgetParticulars() {
  const { categories, departments, particulars, addParticular, updateParticular, deleteParticular } = useBudget()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [categoryId, setCategoryId] = useState("")
  const [departmentId, setDepartmentId] = useState("")
  const [accountCode, setAccountCode] = useState("")
  const [accountName, setAccountName] = useState("")
  const [particular, setParticular] = useState("")
  const [description, setDescription] = useState("")
  const [filterCategory, setFilterCategory] = useState("all")
  const [filterDepartment, setFilterDepartment] = useState("all")
  const [searchQuery, setSearchQuery] = useState("")

  const filtered = particulars.filter((p) => {
    const matchCat = filterCategory === "all" || p.categoryId === filterCategory
    const matchDept = filterDepartment === "all" || p.departmentId === filterDepartment
    const matchSearch =
      p.particular.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.accountCode.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.accountName.toLowerCase().includes(searchQuery.toLowerCase())
    return matchCat && matchDept && matchSearch
  })

  function getCategoryName(id: string) {
    return categories.find((c) => c.id === id)?.name || "Unknown"
  }

  function getDepartmentName(id: string) {
    return departments.find((d) => d.id === id)?.name || "Unknown"
  }

  function openNew() {
    setEditId(null)
    setCategoryId("")
    setDepartmentId("")
    setAccountCode("")
    setAccountName("")
    setParticular("")
    setDescription("")
    setDialogOpen(true)
  }

  function openEdit(id: string) {
    const par = particulars.find((p) => p.id === id)
    if (!par) return
    setEditId(id)
    setCategoryId(par.categoryId)
    setDepartmentId(par.departmentId)
    setAccountCode(par.accountCode)
    setAccountName(par.accountName)
    setParticular(par.particular)
    setDescription(par.description)
    setDialogOpen(true)
  }

  function handleSave() {
    if (!categoryId || !departmentId || !particular.trim()) return
    const data = {
      categoryId,
      departmentId,
      accountCode: accountCode.trim(),
      accountName: accountName.trim(),
      particular: particular.trim(),
      description: description.trim(),
    }
    if (editId) {
      updateParticular(editId, data)
    } else {
      addParticular(data)
    }
    setDialogOpen(false)
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-bold text-[#1a2744]">Budget Particulars</h3>
          <p className="text-sm text-muted-foreground">Manage budget line items by responsibility center</p>
        </div>
        <Button onClick={openNew} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
          <Plus className="mr-1.5 h-4 w-4" />
          New Particular
        </Button>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <Select value={filterCategory} onValueChange={setFilterCategory}>
          <SelectTrigger className="w-56">
            <SelectValue placeholder="Filter by category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Categories</SelectItem>
            {categories.map((cat) => (
              <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={filterDepartment} onValueChange={setFilterDepartment}>
          <SelectTrigger className="w-56">
            <SelectValue placeholder="Filter by department" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Departments</SelectItem>
            {departments.map((dept) => (
              <SelectItem key={dept.id} value={dept.id}>{dept.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input
          placeholder="Search particulars..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-64"
        />
      </div>

      <div className="rounded border border-border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-[#1a2744]">
              <TableHead className="font-semibold text-white">Category</TableHead>
              <TableHead className="font-semibold text-white">Responsibility Center</TableHead>
              <TableHead className="font-semibold text-white">Account</TableHead>
              <TableHead className="font-semibold text-white">Particular</TableHead>
              <TableHead className="font-semibold text-white">Description</TableHead>
              <TableHead className="w-24 text-center font-semibold text-white">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                  No particulars found
                </TableCell>
              </TableRow>
            ) : (
              filtered.map((par, idx) => (
                <TableRow key={par.id} className={idx % 2 === 0 ? "bg-card" : "bg-muted/30"}>
                  <TableCell className="font-medium text-[#1a2744]">{getCategoryName(par.categoryId)}</TableCell>
                  <TableCell className="text-[#1a2744]">{getDepartmentName(par.departmentId)}</TableCell>
                  <TableCell>
                    <div>
                      <p className="text-sm font-mono text-[#1a2744]">{par.accountCode}</p>
                      <p className="text-xs text-muted-foreground">{par.accountName}</p>
                    </div>
                  </TableCell>
                  <TableCell className="text-[#1a2744]">{par.particular}</TableCell>
                  <TableCell className="text-muted-foreground">{par.description}</TableCell>
                  <TableCell>
                    <div className="flex items-center justify-center gap-1">
                      <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(par.id)}>
                        <Pencil className="h-3.5 w-3.5" />
                        <span className="sr-only">Edit</span>
                      </Button>
                      <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:text-destructive" onClick={() => deleteParticular(par.id)}>
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
        <div className="border-t border-border bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
          Total Records: {filtered.length}
        </div>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="text-[#1a2744]">{editId ? "Edit Particular" : "New Budget Particular"}</DialogTitle>
            <DialogDescription>
              {editId ? "Update the particular details." : "Add a new budget particular with responsibility center."}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Category</Label>
              <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger><SelectValue placeholder="Select a category" /></SelectTrigger>
                <SelectContent>
                  {categories.map((cat) => (
                    <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Responsibility Center / Department</Label>
              <Select value={departmentId} onValueChange={setDepartmentId}>
                <SelectTrigger><SelectValue placeholder="Select department" /></SelectTrigger>
                <SelectContent>
                  {departments.map((dept) => (
                    <SelectItem key={dept.id} value={dept.id}>{dept.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="acct-code">Account Code</Label>
                <Input id="acct-code" value={accountCode} onChange={(e) => setAccountCode(e.target.value)} placeholder="e.g. 4-1005" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="acct-name">Account Name</Label>
                <Input id="acct-name" value={accountName} onChange={(e) => setAccountName(e.target.value)} placeholder="e.g. College - Information System" />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="par-name">Particular</Label>
              <Input id="par-name" value={particular} onChange={(e) => setParticular(e.target.value)} placeholder="e.g. IT Operations" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="par-desc">Description</Label>
              <Input id="par-desc" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Brief description" />
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
