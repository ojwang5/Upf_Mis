import { ReactNode } from "react";
import { Link, useLocation } from "wouter";
import Header from "./Header";
import {
  Home,
  ClipboardEdit,
  Users,
  History,
  BarChart4,
  ChevronRight,
  UserCircle,
  LogOut,
  Menu,
} from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { useIsMobile } from "@/hooks/use-mobile";

interface LayoutProps {
  children: ReactNode;
  title: string;
  description?: string;
}

export default function Layout({ children, title, description }: LayoutProps) {
  const { user, logoutMutation } = useAuth();
  const [location] = useLocation();
  const isMobile = useIsMobile();
  
  const isAdmin = user?.role === "admin";
  
  const navigation = [
    {
      name: "Dashboard",
      href: "/",
      icon: Home,
      active: location === "/",
    },
    {
      name: "Daily Status",
      href: "/daily-status",
      icon: ClipboardEdit,
      active: location === "/daily-status",
    },
    {
      name: "Employees",
      href: "/employees",
      icon: Users,
      active: location === "/employees",
    },
    {
      name: "History",
      href: "/history",
      icon: History,
      active: location === "/history",
    },
    {
      name: "Reports",
      href: "/reports",
      icon: BarChart4,
      active: location === "/reports",
    },
  ];
  
  // Only show the profile link for admin users
  if (isAdmin) {
    navigation.push({
      name: "Profile",
      href: "/profile",
      icon: UserCircle,
      active: location === "/profile",
    });
  }
  
  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Header />
      
      <div className="flex-1 flex flex-col md:flex-row">
        {/* Sidebar - desktop only */}
        {!isMobile && (
          <div className="w-64 bg-white border-r border-gray-200 hidden md:block">
            <div className="h-full py-6 pl-4 pr-2">
              <nav className="space-y-1">
                {navigation.map((item) => (
                  <Link
                    key={item.name}
                    href={item.href}
                    className={cn(
                      "flex items-center px-3 py-2.5 text-sm font-medium rounded-lg",
                      item.active
                        ? "bg-navy-50 text-navy-700"
                        : "text-gray-600 hover:bg-gray-50"
                    )}
                  >
                    <item.icon
                      className={cn(
                        "mr-3 h-5 w-5 flex-shrink-0",
                        item.active ? "text-navy-600" : "text-gray-400"
                      )}
                    />
                    <span>{item.name}</span>
                    {item.active && (
                      <ChevronRight className="ml-auto h-4 w-4 text-navy-600" />
                    )}
                  </Link>
                ))}
              </nav>
              
              <div className="mt-6 pt-6 border-t border-gray-200">
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full flex items-center justify-center"
                  onClick={() => logoutMutation.mutate()}
                >
                  <LogOut className="mr-2 h-4 w-4" />
                  Logout
                </Button>
              </div>
            </div>
          </div>
        )}
        
        {/* Main content */}
        <main className="flex-1 p-4 sm:p-6 md:p-8">
          <div className="max-w-7xl mx-auto">
            <div className="mb-6">
              <h1 className="text-2xl font-bold tracking-tight text-gray-900">
                {title}
              </h1>
              {description && (
                <p className="mt-1 text-sm text-gray-500">{description}</p>
              )}
            </div>
            
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}