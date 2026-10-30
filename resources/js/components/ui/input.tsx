import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-[#2E2E2E] bg-[#1F1F1F] text-[#F5F5F5] placeholder:text-[#666666] selection:bg-[#AFFA01] selection:text-black flex h-11 w-full min-w-0 rounded-xl border px-4 py-2 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-[#AFFA01] focus-visible:ring-[#AFFA01]/30 focus-visible:ring-[2px]",
        "aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
