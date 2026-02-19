"use client"

import { useState } from "react"
import { useBudget } from "@/lib/budget-context"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
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

export function BudgetCategories() {
  const { categories, addCategory, updateCategory, deleteCategory } = useBudget()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [name, setName] = useState("")
  const [description, setDescription] = useState("")
  const [searchQuery, setSearchQuery] = useState("")

  const filtered = categories.filter(
    (c) =>
      c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      c.description.toLowerCase().includes(searchQuery.toLowerCase())
  )

  function openNew() {
    setEditId(null)
    setName("")
    setDescription("")
    setDialogOpen(true)
  }

  function openEdit(id: string) {
    const cat = categories.find((c) => c.id === id)
    if (!cat) return
    setEditId(id)
    setName(cat.name)
    setDescription(cat.description)
    setDialogOpen(true)
  }

  function handleSave() {
    if (!name.trim()) return
    if (editId) {
      updateCategory(editId, { name: name.trim().toUpperCase(), description: description.trim() })
    } else {
      addCategory({ name: name.trim().toUpperCase(), description: description.trim() })
    }
    setDialogOpen(false)
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-lg font-bold text-[#1a2744]">Budget Categories</h3>
          <p className="text-sm text-muted-foreground">Manage budget fund categories</p>
        </div>
        <div className="flex items-center gap-2">
          <Input
            placeholder="Search categories..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-64"
          />
          <Button onClick={openNew} size="sm" className="bg-[#1a2744] text-white hover:bg-[#243352]">
            <Plus className="mr-1.5 h-4 w-4" />
            New Category
          </Button>
        </div>
      </div>

      <div className="rounded border border-border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-[#1a2744]">
              <TableHead className="font-semibold text-white">Category</TableHead>
              <TableHead className="font-semibold text-white">Description</TableHead>
              <TableHead className="w-24 text-center font-semibold text-white">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.length === 0 ? (
              <TableRow>
                <TableCell colSpan={3} className="py-8 text-center text-muted-foreground">
                  No categories found
                </TableCell>
              </TableRow>
            ) : (
              filtered.map((cat, idx) => (
                <TableRow key={cat.id} className={idx % 2 === 0 ? "bg-card" : "bg-muted/30"}>
                  <TableCell className="font-semibold text-[#1a2744]">{cat.name}</TableCell>
                  <TableCell className="text-muted-foreground">{cat.description}</TableCell>
                  <TableCell>
                    <div className="flex items-center justify-center gap-1">
                      <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(cat.id)}>
                        <Pencil className="h-3.5 w-3.5" />
                        <span className="sr-only">Edit</span>
                      </Button>
                      <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:text-destructive" onClick={() => deleteCategory(cat.id)}>
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
            <DialogTitle className="text-[#1a2744]">{editId ? "Edit Category" : "New Budget Category"}</DialogTitle>
            <DialogDescription>
              {editId ? "Update the budget category details." : "Add a new budget fund category."}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="cat-name">Category Name</Label>
              <Input id="cat-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. AUXILIARY FUND" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="cat-desc">Description</Label>
              <Input id="cat-desc" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="e.g. Income Generating Projects" />
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
