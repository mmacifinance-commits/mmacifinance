"use client"

import * as React from "react"
import Image from "next/image"
import { useRouter } from "next/navigation"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Loader2, ArrowRight, ArrowLeft } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp"
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from "@/components/ui/form"
import { toast } from "sonner"

const loginSchema = z.object({
    email: z.string().email({ message: "Invalid email address" }),
    password: z.string().min(6, { message: "Password must be at least 6 characters" }),
})

const otpSchema = z.object({
    code: z.string().min(6, { message: "Your one-time password must be 6 characters." }),
})

type LoginFormValues = z.infer<typeof loginSchema>
type OTPFormValues = z.infer<typeof otpSchema>

export function LoginForm() {
    const [step, setStep] = React.useState<"credentials" | "otp">("credentials")
    const [isLoading, setIsLoading] = React.useState<boolean>(false)
    const [email, setEmail] = React.useState<string>("")
    const router = useRouter()

    const loginForm = useForm<LoginFormValues>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: "",
            password: "",
        },
    })

    const otpForm = useForm<OTPFormValues>({
        resolver: zodResolver(otpSchema),
        defaultValues: {
            code: "",
        },
    })

    async function onLoginSubmit(data: LoginFormValues) {
        setIsLoading(true)
        setEmail(data.email)

        // Simulate API call
        setTimeout(() => {
            setIsLoading(false)
            setStep("otp")
            toast.info("Verification code sent", {
                description: "Please check your email for the 2FA code.",
            })
        }, 1000)
    }

    async function onOTPSubmit(data: OTPFormValues) {
        setIsLoading(true)

        // Simulate API call
        setTimeout(() => {
            setIsLoading(false)
            toast.success("Login successful", {
                description: "Welcome back!",
            })
            router.push("/")
        }, 1000)
    }

    return (
        <div className="flex flex-col items-center justify-center space-y-4">
            <Card className="w-full max-w-md border-t-4 border-t-[#d4a843] shadow-lg">
                <CardHeader className="space-y-1 text-center">
                    {/* Logo */}
                    <div className="flex justify-center mb-2">
                        <Image
                            src="/images/logo.jpg"
                            alt="MMACI Logo"
                            width={80}
                            height={80}
                            className="rounded-full border-4 border-[#d4a843]"
                        />
                    </div>
                    <CardTitle className="text-2xl font-bold text-[#243352]">
                        {step === "credentials" ? "Welcome Back" : "Two-Factor Authentication"}
                    </CardTitle>
                    <CardDescription>
                        {step === "credentials"
                            ? "Enter your credentials to access your account"
                            : `We sent a code to ${email}`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {step === "credentials" ? (
                        <Form {...loginForm}>
                            <form onSubmit={loginForm.handleSubmit(onLoginSubmit)} className="space-y-4">
                                <FormField
                                    control={loginForm.control}
                                    name="email"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Email</FormLabel>
                                            <FormControl>
                                                <Input
                                                    placeholder="name@example.com"
                                                    type="email"
                                                    autoCapitalize="none"
                                                    autoComplete="email"
                                                    autoCorrect="off"
                                                    disabled={isLoading}
                                                    {...field}
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={loginForm.control}
                                    name="password"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Password</FormLabel>
                                            <FormControl>
                                                <Input
                                                    placeholder="••••••••"
                                                    type="password"
                                                    autoComplete="current-password"
                                                    disabled={isLoading}
                                                    {...field}
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <Button
                                    className="w-full bg-[#243352] text-white hover:bg-[#1a2744]"
                                    type="submit"
                                    disabled={isLoading}
                                >
                                    {isLoading ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : (
                                        <>
                                            Sign In <ArrowRight className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </form>
                        </Form>
                    ) : (
                        <form onSubmit={otpForm.handleSubmit(onOTPSubmit)} className="space-y-6">
                            <div className="flex flex-col items-center justify-center space-y-2">
                                <InputOTP
                                    maxLength={6}
                                    value={otpForm.watch("code")}
                                    onChange={(val) => otpForm.setValue("code", val)}
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot index={0} />
                                        <InputOTPSlot index={1} />
                                        <InputOTPSlot index={2} />
                                        <InputOTPSlot index={3} />
                                        <InputOTPSlot index={4} />
                                        <InputOTPSlot index={5} />
                                    </InputOTPGroup>
                                </InputOTP>
                                <p className="text-xs text-muted-foreground text-center">
                                    Please enter the 6-digit code sent to your email.
                                </p>
                                {otpForm.formState.errors.code && (
                                    <p className="text-sm text-destructive">
                                        {otpForm.formState.errors.code.message}
                                    </p>
                                )}
                            </div>

                            <Button
                                className="w-full bg-[#d4a843] text-[#243352] hover:bg-[#b8913a]"
                                type="submit"
                                disabled={isLoading}
                            >
                                {isLoading && (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                Verify Code
                            </Button>
                        </form>
                    )}
                </CardContent>
                <CardFooter className="flex flex-col space-y-2">
                    {step === "otp" && (
                        <Button
                            variant="link"
                            className="px-0 text-sm text-muted-foreground"
                            onClick={() => setStep("credentials")}
                            disabled={isLoading}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" /> Back to Login
                        </Button>
                    )}
                    <div className="text-center text-xs text-muted-foreground">
                        Merchant Marine Academy of Caraga, Inc.
                    </div>
                </CardFooter>
            </Card>
        </div>
    )
}
