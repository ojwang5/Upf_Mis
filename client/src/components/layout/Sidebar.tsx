import { useLocation, Link } from "wouter";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Users,
  ClipboardList,
  BarChart3,
  History,
  Menu
} from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { useState } from "react";
import { Button } from "@/components/ui/button";

export default function Sidebar() {
  const [location] = useLocation();
  const { user } = useAuth();
  const [isOpen, setIsOpen] = useState(false);

  const toggleSidebar = () => setIsOpen(!isOpen);

  const navItems = [
    {
      label: "Dashboard",
      href: "/",
      icon: <LayoutDashboard size={18} />
    },
    {
      label: "Employees",
      href: "/employees",
      icon: <Users size={18} />
    },
    {
      label: "Daily Status",
      href: "/daily-status",
      icon: <ClipboardList size={18} />
    },
    {
      label: "Reports",
      href: "/reports",
      icon: <BarChart3 size={18} />
    },
    {
      label: "History",
      href: "/history",
      icon: <History size={18} />
    }
  ];

  return (
    <>
      <Button
        variant="outline"
        size="icon"
        className="fixed top-24 left-4 z-50 md:hidden"
        onClick={toggleSidebar}
      >
        <Menu size={20} />
      </Button>

      <aside 
        className={cn(
          "bg-white border-r border-slate-200 transition-all duration-300 w-64 flex-shrink-0 shadow-sm",
          isOpen ? "fixed inset-y-0 left-0 z-40" : "hidden md:block"
        )}
      >
        <div className="p-4 pt-5">
          <div className="text-center mb-6">
            <p className="text-sm text-slate-500">Logged in as:</p>
            <p className="font-semibold text-navy-700">{user?.fullName}</p>
            {user?.branchAccess && (
              <p className="text-xs text-slate-600 mt-1 capitalize">
                {user.branchAccess.replace('_', ' ')} Branch
              </p>
            )}
          </div>
          
          <nav>
            <ul className="space-y-1">
              {navItems.map((item) => (
                <li key={item.href}>
                  <Link href={item.href}>
                    <div
                      className={cn(
                        "flex items-center gap-3 px-3 py-2 rounded-md transition-colors cursor-pointer",
                        location === item.href
                          ? "bg-navy-50 text-navy-800 font-medium"
                          : "text-slate-600 hover:bg-slate-100"
                      )}
                    >
                      {item.icon}
                      {item.label}
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        </div>
      </aside>
      
      {/* Backdrop for mobile */}
      {isOpen && (
        <div 
          className="fixed inset-0 bg-black/30 z-30 md:hidden" 
          onClick={toggleSidebar}
        />
      )}
    </>
  );
}