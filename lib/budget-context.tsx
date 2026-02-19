"use client"

import React, { createContext, useContext, useState, useCallback } from "react"
import type {
  Department,
  BudgetCategory,
  BudgetParticular,
  AnnualBudget,
  BudgetItem,
  Expense,
  Disbursement,
} from "./store"
import {
  defaultDepartments,
  defaultCategories,
  defaultParticulars,
  defaultBudgets,
  defaultExpenses,
  defaultDisbursements,
  generateId,
  generateRefNo,
} from "./store"

interface BudgetContextType {
  departments: Department[]
  categories: BudgetCategory[]
  particulars: BudgetParticular[]
  budgets: AnnualBudget[]
  expenses: Expense[]
  disbursements: Disbursement[]
  addDepartment: (dept: Omit<Department, "id">) => void
  updateDepartment: (id: string, dept: Partial<Department>) => void
  deleteDepartment: (id: string) => void
  addCategory: (cat: Omit<BudgetCategory, "id">) => void
  updateCategory: (id: string, cat: Partial<BudgetCategory>) => void
  deleteCategory: (id: string) => void
  addParticular: (par: Omit<BudgetParticular, "id">) => void
  updateParticular: (id: string, par: Partial<BudgetParticular>) => void
  deleteParticular: (id: string) => void
  addBudget: (year: number) => void
  addBudgetItem: (budgetId: string, item: Omit<BudgetItem, "id" | "budgetId">) => void
  updateBudgetItem: (budgetId: string, itemId: string, updates: Partial<BudgetItem>) => void
  deleteBudgetItem: (budgetId: string, itemId: string) => void
  deleteBudget: (id: string) => void
  addExpense: (exp: Omit<Expense, "id" | "refNo">) => void
  updateExpense: (id: string, exp: Partial<Expense>) => void
  deleteExpense: (id: string) => void
  addDisbursement: (dsb: Omit<Disbursement, "id" | "disbursementNo">) => void
  updateDisbursement: (id: string, dsb: Partial<Disbursement>) => void
  deleteDisbursement: (id: string) => void
}

const BudgetContext = createContext<BudgetContextType | null>(null)

export function BudgetProvider({ children }: { children: React.ReactNode }) {
  const [departments, setDepartments] = useState<Department[]>(defaultDepartments)
  const [categories, setCategories] = useState<BudgetCategory[]>(defaultCategories)
  const [particulars, setParticulars] = useState<BudgetParticular[]>(defaultParticulars)
  const [budgets, setBudgets] = useState<AnnualBudget[]>(defaultBudgets)
  const [expenses, setExpenses] = useState<Expense[]>(defaultExpenses)
  const [disbursements, setDisbursements] = useState<Disbursement[]>(defaultDisbursements)

  const addDepartment = useCallback((dept: Omit<Department, "id">) => {
    setDepartments((prev) => [...prev, { ...dept, id: generateId("dept") }])
  }, [])

  const updateDepartment = useCallback((id: string, updates: Partial<Department>) => {
    setDepartments((prev) => prev.map((d) => (d.id === id ? { ...d, ...updates } : d)))
  }, [])

  const deleteDepartment = useCallback((id: string) => {
    setDepartments((prev) => prev.filter((d) => d.id !== id))
  }, [])

  const addCategory = useCallback((cat: Omit<BudgetCategory, "id">) => {
    setCategories((prev) => [...prev, { ...cat, id: generateId("cat") }])
  }, [])

  const updateCategory = useCallback((id: string, updates: Partial<BudgetCategory>) => {
    setCategories((prev) => prev.map((c) => (c.id === id ? { ...c, ...updates } : c)))
  }, [])

  const deleteCategory = useCallback((id: string) => {
    setCategories((prev) => prev.filter((c) => c.id !== id))
  }, [])

  const addParticular = useCallback((par: Omit<BudgetParticular, "id">) => {
    setParticulars((prev) => [...prev, { ...par, id: generateId("par") }])
  }, [])

  const updateParticular = useCallback((id: string, updates: Partial<BudgetParticular>) => {
    setParticulars((prev) => prev.map((p) => (p.id === id ? { ...p, ...updates } : p)))
  }, [])

  const deleteParticular = useCallback((id: string) => {
    setParticulars((prev) => prev.filter((p) => p.id !== id))
  }, [])

  const addBudget = useCallback((year: number) => {
    setBudgets((prev) => [...prev, { id: generateId("budget"), year, items: [] }])
  }, [])

  const addBudgetItem = useCallback(
    (budgetId: string, item: Omit<BudgetItem, "id" | "budgetId">) => {
      setBudgets((prev) =>
        prev.map((b) =>
          b.id === budgetId
            ? { ...b, items: [...b.items, { ...item, id: generateId("bi"), budgetId }] }
            : b
        )
      )
    },
    []
  )

  const updateBudgetItem = useCallback(
    (budgetId: string, itemId: string, updates: Partial<BudgetItem>) => {
      setBudgets((prev) =>
        prev.map((b) =>
          b.id === budgetId
            ? { ...b, items: b.items.map((i) => (i.id === itemId ? { ...i, ...updates } : i)) }
            : b
        )
      )
    },
    []
  )

  const deleteBudgetItem = useCallback((budgetId: string, itemId: string) => {
    setBudgets((prev) =>
      prev.map((b) =>
        b.id === budgetId ? { ...b, items: b.items.filter((i) => i.id !== itemId) } : b
      )
    )
  }, [])

  const deleteBudget = useCallback((id: string) => {
    setBudgets((prev) => prev.filter((b) => b.id !== id))
  }, [])

  const addExpense = useCallback((exp: Omit<Expense, "id" | "refNo">) => {
    setExpenses((prev) => [
      ...prev,
      { ...exp, id: generateId("exp"), refNo: generateRefNo("EXP", prev.length) },
    ])
  }, [])

  const updateExpense = useCallback((id: string, updates: Partial<Expense>) => {
    setExpenses((prev) => prev.map((e) => (e.id === id ? { ...e, ...updates } : e)))
  }, [])

  const deleteExpense = useCallback((id: string) => {
    setExpenses((prev) => prev.filter((e) => e.id !== id))
  }, [])

  const addDisbursement = useCallback((dsb: Omit<Disbursement, "id" | "disbursementNo">) => {
    setDisbursements((prev) => [
      ...prev,
      { ...dsb, id: generateId("dsb"), disbursementNo: generateRefNo("DSB", prev.length) },
    ])
  }, [])

  const updateDisbursement = useCallback((id: string, updates: Partial<Disbursement>) => {
    setDisbursements((prev) => prev.map((d) => (d.id === id ? { ...d, ...updates } : d)))
  }, [])

  const deleteDisbursement = useCallback((id: string) => {
    setDisbursements((prev) => prev.filter((d) => d.id !== id))
  }, [])

  return (
    <BudgetContext.Provider
      value={{
        departments,
        categories,
        particulars,
        budgets,
        expenses,
        disbursements,
        addDepartment,
        updateDepartment,
        deleteDepartment,
        addCategory,
        updateCategory,
        deleteCategory,
        addParticular,
        updateParticular,
        deleteParticular,
        addBudget,
        addBudgetItem,
        updateBudgetItem,
        deleteBudgetItem,
        deleteBudget,
        addExpense,
        updateExpense,
        deleteExpense,
        addDisbursement,
        updateDisbursement,
        deleteDisbursement,
      }}
    >
      {children}
    </BudgetContext.Provider>
  )
}

export function useBudget() {
  const ctx = useContext(BudgetContext)
  if (!ctx) throw new Error("useBudget must be used within BudgetProvider")
  return ctx
}
