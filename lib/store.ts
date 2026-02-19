// Types
export interface Department {
  id: string
  name: string
  code: string
}

export interface BudgetCategory {
  id: string
  name: string
  description: string
}

export interface BudgetParticular {
  id: string
  categoryId: string
  departmentId: string
  accountCode: string
  accountName: string
  particular: string
  description: string
}

export interface AnnualBudget {
  id: string
  year: number
  items: BudgetItem[]
}

export interface BudgetItem {
  id: string
  budgetId: string
  categoryId: string
  particularId: string
  appropriation: number
  expenditure: number
}

export interface Expense {
  id: string
  refNo: string
  description: string
  categoryId: string
  particularId: string
  amount: number
  paid: number
  dateEncoded: string
  dateApproved: string | null
  status: "pending" | "approved" | "cancelled"
  notes: string
}

export interface Disbursement {
  id: string
  disbursementNo: string
  description: string
  source: string
  payTo: string
  amount: number
  method: "check" | "cash" | "bank_transfer"
  dateEncoded: string
  dateApproved: string | null
  status: "pending" | "approved" | "posted" | "cancelled"
  notes: string
}

// Sample Data
export const defaultDepartments: Department[] = [
  { id: "dept-1", name: "College - Marine Transportation", code: "MT" },
  { id: "dept-2", name: "College - Marine Engineering", code: "ME" },
  { id: "dept-3", name: "College - Information System", code: "IS" },
  { id: "dept-4", name: "Senior High School", code: "SHS" },
  { id: "dept-5", name: "Administration", code: "ADMIN" },
]

export const defaultCategories: BudgetCategory[] = [
  { id: "cat-1", name: "AUXILIARY FUND", description: "Income Generating Projects and Other Income" },
  { id: "cat-2", name: "TRUST FUND", description: "Trust Liabilities" },
  { id: "cat-3", name: "TUITION AND OTHER FEES", description: "Tuition Fee, Miscellaneous, and Laboratory Fees" },
  { id: "cat-4", name: "LABORATORY FEES", description: "College Laboratory and Driving Fees" },
  { id: "cat-5", name: "MISCELLANEOUS INCOME", description: "Energy Fee, Testing Fee, Registration, and Other Fees" },
]

export const defaultParticulars: BudgetParticular[] = [
  { id: "par-1", categoryId: "cat-1", departmentId: "dept-1", accountCode: "5-1212", accountName: "Insurance Expense - Students - Marine Transportation", particular: "Insurance Expense", description: "Student insurance coverage" },
  { id: "par-2", categoryId: "cat-1", departmentId: "dept-1", accountCode: "5-1211", accountName: "Graduation Expense - Marine Transportation", particular: "Graduation Expense", description: "Graduation ceremony costs" },
  { id: "par-3", categoryId: "cat-1", departmentId: "dept-3", accountCode: "4-1005", accountName: "College - Information System", particular: "IS Operations", description: "Information System operations" },
  { id: "par-4", categoryId: "cat-2", departmentId: "dept-2", accountCode: "5-1114", accountName: "Medical and Dental - Marine Engineering", particular: "Medical-Dental Fund", description: "Medical and dental services" },
  { id: "par-5", categoryId: "cat-2", departmentId: "dept-3", accountCode: "4-1005", accountName: "College - Information System", particular: "Trust IT Fund", description: "IT trust fund allocation" },
  { id: "par-6", categoryId: "cat-3", departmentId: "dept-3", accountCode: "4-1005", accountName: "College - Information System", particular: "IT Tuition Allocation", description: "IT allocation from tuition" },
  { id: "par-7", categoryId: "cat-3", departmentId: "dept-2", accountCode: "4-1001", accountName: "College - Marine Engineering", particular: "Marine Engineering Fund", description: "Marine engineering program fees" },
  { id: "par-8", categoryId: "cat-4", departmentId: "dept-3", accountCode: "4-1070", accountName: "College Lab Fee", particular: "Laboratory Supplies", description: "Lab supplies and equipment" },
  { id: "par-9", categoryId: "cat-4", departmentId: "dept-1", accountCode: "4-1071", accountName: "Driving Fee", particular: "Driving Course", description: "Driving course fees" },
  { id: "par-10", categoryId: "cat-5", departmentId: "dept-5", accountCode: "4-3017", accountName: "Energy Fee", particular: "Energy Fee", description: "Utility and energy expenses" },
  { id: "par-11", categoryId: "cat-5", departmentId: "dept-5", accountCode: "4-3019", accountName: "Testing Fee", particular: "Testing Fee", description: "Assessment and testing costs" },
  { id: "par-12", categoryId: "cat-5", departmentId: "dept-5", accountCode: "4-3024", accountName: "Registration Fee", particular: "Registration Fee", description: "Student registration processing" },
]

export const defaultBudgets: AnnualBudget[] = [
  {
    id: "budget-2025",
    year: 2025,
    items: [
      { id: "bi-1", budgetId: "budget-2025", categoryId: "cat-1", particularId: "par-1", appropriation: 2000000, expenditure: 450000 },
      { id: "bi-2", budgetId: "budget-2025", categoryId: "cat-1", particularId: "par-2", appropriation: 4000000, expenditure: 1200000 },
      { id: "bi-3", budgetId: "budget-2025", categoryId: "cat-1", particularId: "par-3", appropriation: 6000000, expenditure: 2800000 },
      { id: "bi-4", budgetId: "budget-2025", categoryId: "cat-2", particularId: "par-4", appropriation: 1500000, expenditure: 350000 },
      { id: "bi-5", budgetId: "budget-2025", categoryId: "cat-2", particularId: "par-5", appropriation: 3000000, expenditure: 800000 },
      { id: "bi-6", budgetId: "budget-2025", categoryId: "cat-3", particularId: "par-6", appropriation: 2500000, expenditure: 600000 },
      { id: "bi-7", budgetId: "budget-2025", categoryId: "cat-3", particularId: "par-7", appropriation: 5000000, expenditure: 3000000 },
      { id: "bi-8", budgetId: "budget-2025", categoryId: "cat-4", particularId: "par-8", appropriation: 2522500, expenditure: 980000 },
      { id: "bi-9", budgetId: "budget-2025", categoryId: "cat-4", particularId: "par-9", appropriation: 280000, expenditure: 120000 },
      { id: "bi-10", budgetId: "budget-2025", categoryId: "cat-5", particularId: "par-10", appropriation: 2965005, expenditure: 1450000 },
      { id: "bi-11", budgetId: "budget-2025", categoryId: "cat-5", particularId: "par-11", appropriation: 960452, expenditure: 320000 },
      { id: "bi-12", budgetId: "budget-2025", categoryId: "cat-5", particularId: "par-12", appropriation: 1441000, expenditure: 720000 },
    ],
  },
  {
    id: "budget-2026",
    year: 2026,
    items: [
      { id: "bi-13", budgetId: "budget-2026", categoryId: "cat-1", particularId: "par-1", appropriation: 2200000, expenditure: 0 },
      { id: "bi-14", budgetId: "budget-2026", categoryId: "cat-1", particularId: "par-2", appropriation: 4500000, expenditure: 0 },
      { id: "bi-15", budgetId: "budget-2026", categoryId: "cat-1", particularId: "par-3", appropriation: 6500000, expenditure: 0 },
      { id: "bi-16", budgetId: "budget-2026", categoryId: "cat-2", particularId: "par-4", appropriation: 1800000, expenditure: 0 },
      { id: "bi-17", budgetId: "budget-2026", categoryId: "cat-2", particularId: "par-5", appropriation: 3500000, expenditure: 0 },
      { id: "bi-18", budgetId: "budget-2026", categoryId: "cat-3", particularId: "par-6", appropriation: 3000000, expenditure: 0 },
      { id: "bi-19", budgetId: "budget-2026", categoryId: "cat-3", particularId: "par-7", appropriation: 5500000, expenditure: 0 },
    ],
  },
]

export const defaultExpenses: Expense[] = [
  {
    id: "exp-1",
    refNo: "EXP25000001",
    description: "TUITION AND OTHER FEES",
    categoryId: "cat-3",
    particularId: "par-7",
    amount: 3000000,
    paid: 3000000,
    dateEncoded: "2025-01-15",
    dateApproved: "2025-01-20",
    status: "approved",
    notes: "Marine Engineering tuition allocation Q1",
  },
  {
    id: "exp-2",
    refNo: "EXP25000002",
    description: "AUXILIARY FUND",
    categoryId: "cat-1",
    particularId: "par-2",
    amount: 1200000,
    paid: 1200000,
    dateEncoded: "2025-02-10",
    dateApproved: "2025-02-15",
    status: "approved",
    notes: "Graduation expenses batch 1",
  },
  {
    id: "exp-3",
    refNo: "EXP25000003",
    description: "TRUST FUND",
    categoryId: "cat-2",
    particularId: "par-4",
    amount: 350000,
    paid: 200000,
    dateEncoded: "2025-03-05",
    dateApproved: null,
    status: "pending",
    notes: "Medical supplies procurement",
  },
  {
    id: "exp-4",
    refNo: "EXP25000004",
    description: "MISCELLANEOUS INCOME",
    categoryId: "cat-5",
    particularId: "par-10",
    amount: 1450000,
    paid: 1450000,
    dateEncoded: "2025-03-20",
    dateApproved: "2025-03-25",
    status: "approved",
    notes: "Energy utility payments Q1",
  },
  {
    id: "exp-5",
    refNo: "EXP25000005",
    description: "LABORATORY FEES",
    categoryId: "cat-4",
    particularId: "par-8",
    amount: 980000,
    paid: 500000,
    dateEncoded: "2025-04-01",
    dateApproved: null,
    status: "pending",
    notes: "Lab equipment purchase",
  },
]

export const defaultDisbursements: Disbursement[] = [
  {
    id: "dsb-1",
    disbursementNo: "DSB25000001",
    description: "Computer Equipment",
    source: "Expenditure",
    payTo: "Tech Solutions Inc.",
    amount: 850000,
    method: "check",
    dateEncoded: "2025-01-25",
    dateApproved: "2025-01-30",
    status: "posted",
    notes: "IT Lab computers procurement",
  },
  {
    id: "dsb-2",
    disbursementNo: "DSB25000002",
    description: "Graduation Venue",
    source: "Expenditure",
    payTo: "Grand Astoria Hotel",
    amount: 400000,
    method: "bank_transfer",
    dateEncoded: "2025-02-20",
    dateApproved: "2025-02-25",
    status: "approved",
    notes: "Venue rental for graduation ceremony",
  },
  {
    id: "dsb-3",
    disbursementNo: "DSB25000003",
    description: "Medical Supplies",
    source: "Expenditure",
    payTo: "PhilHealth Medical Supply",
    amount: 200000,
    method: "check",
    dateEncoded: "2025-03-10",
    dateApproved: null,
    status: "pending",
    notes: "Clinic medical supplies",
  },
  {
    id: "dsb-4",
    disbursementNo: "DSB25000004",
    description: "Energy Bill Payment",
    source: "Expenditure",
    payTo: "BOHECO",
    amount: 750000,
    method: "bank_transfer",
    dateEncoded: "2025-03-28",
    dateApproved: "2025-04-02",
    status: "posted",
    notes: "Electricity bill Jan-Mar 2025",
  },
]

// Helper functions
export function generateId(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

export function generateRefNo(prefix: string, count: number): string {
  const year = new Date().getFullYear().toString().slice(-2)
  return `${prefix}${year}${String(count + 1).padStart(6, "0")}`
}
