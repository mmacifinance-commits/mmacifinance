import { LoginForm } from "@/components/login-form"

export default function LoginPage() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#f0f4f8] p-4 relative overflow-hidden">
            {/* Background Decorative Elements */}
            <div className="absolute top-0 left-0 w-full h-full bg-[#243352] origin-top-left"></div>
            {/* Content */}
            <div className="relative z-10 w-full max-w-md">
                <div className="mb-8 text-center">
                    {/* Placeholder for Logo if available, using text for now */}
                </div>
                <LoginForm />
            </div>
        </div>
    )
}
