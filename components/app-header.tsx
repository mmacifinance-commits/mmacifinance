"use client"

import Image from "next/image"
import { User } from "lucide-react"

export function AppHeader() {
  return (
    <header className="bg-[#1a2744] text-white">
      <div className="flex items-center justify-between px-4 py-2 md:px-6">
        <div className="flex items-center gap-3">
          <Image
            src="/images/logo.jpg"
            alt="MMACI Logo"
            width={52}
            height={52}
            className="rounded-full border-2 border-[#d4a843]"
          />
          <div>
            <h1 className="text-base font-bold uppercase leading-tight tracking-wide text-white md:text-lg">
              Accounting Information System
            </h1>
            <p className="text-xs text-[#d4a843]">
              Merchant Marine Academy of Caraga, Inc.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <div className="hidden items-center gap-2 rounded border border-[#d4a843]/30 bg-[#d4a843]/10 px-3 py-1.5 text-sm md:flex">
            <User className="h-4 w-4 text-[#d4a843]" />
            <span className="text-white">Super Admin</span>
          </div>
        </div>
      </div>
    </header>
  )
}
