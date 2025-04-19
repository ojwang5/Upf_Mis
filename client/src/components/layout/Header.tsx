import { Shield, UserCircle, LogOut, Menu } from "lucide-react";
import { useState } from "react";
import { Link, useLocation } from "wouter";
import { useAuth } from "@/hooks/use-auth";
import { Button } from "@/components/ui/button";
import { Sheet, SheetTrigger, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import logoPath from "@assets/logo.jpg";
import { useIsMobile } from "@/hooks/use-mobile";

export default function Header() {
  const { user, logoutMutation } = useAuth();
  const [, setLocation] = useLocation();
  const isMobile = useIsMobile();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  
  // Function to handle logout
  const handleLogout = async () => {
    await logoutMutation.mutateAsync();
    setLocation("/auth");
    setIsMenuOpen(false);
  };
  
  // Get user initials for avatar
  const getUserInitials = () => {
    if (!user?.fullName) return "U";
    
    const nameParts = user.fullName.split(" ");
    if (nameParts.length > 1) {
      return `${nameParts[0][0]}${nameParts[nameParts.length - 1][0]}`;
    }
    return nameParts[0][0] || "U";
  };
  
  // Display badge based on user role
  const getRoleBadge = () => {
    if (user?.role === "admin") {
      return <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">Admin</span>;
    }
    if (user?.role === "branch_manager") {
      return <span className="bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded">Branch Manager</span>;
    }
    return null;
  };
  
  const closeMenu = () => setIsMenuOpen(false);
  
  return (
    <header className="bg-navy-800 text-white border-b border-navy-700 sticky top-0 z-40">
      <div className="container mx-auto px-4 sm:px-6">
        <div className="flex items-center justify-between h-16">
          {/* Logo and title */}
          <div className="flex items-center">
            <Link href="/" className="flex items-center">
              <div className="flex-shrink-0 relative w-10 h-10 rounded-full overflow-hidden border-2 border-white">
                <img 
                  src={logoPath} 
                  alt="Uganda Police Force Logo" 
                  className="w-full h-full object-cover"
                />
              </div>
              
              <div className="hidden md:block ml-4">
                <h1 className="font-bold text-lg">UGANDA POLICE FORCE</h1>
                <div className="flex items-center">
                  <h2 className="text-sm text-navy-100">MDD MANAGEMENT SYSTEM</h2>
                  <span className="text-xs px-2 py-0.5 ml-2 bg-navy-700 rounded">PROTECT & SERVE</span>
                </div>
              </div>
            </Link>
          </div>
          
          {/* Desktop navigation */}
          {!isMobile && (
            <div className="hidden md:flex items-center space-x-4">
              <div className="flex items-center">
                <div className="mr-4 text-right">
                  <div className="flex items-center">
                    <div className="font-medium">{user?.fullName}</div>
                    <div className="ml-2">{getRoleBadge()}</div>
                  </div>
                  <div className="text-sm text-navy-200">{user?.username}</div>
                </div>
                
                <div className="relative">
                  <Avatar className="h-9 w-9 border-2 border-navy-500">
                    <AvatarFallback className="bg-navy-600 text-white">
                      {getUserInitials()}
                    </AvatarFallback>
                  </Avatar>
                </div>
                
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={handleLogout}
                  className="ml-2 text-navy-100 hover:text-white hover:bg-navy-700"
                >
                  <LogOut className="h-5 w-5" />
                </Button>
              </div>
            </div>
          )}
          
          {/* Mobile menu button */}
          {isMobile && (
            <Sheet open={isMenuOpen} onOpenChange={setIsMenuOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="md:hidden text-white">
                  <Menu className="h-6 w-6" />
                </Button>
              </SheetTrigger>
              <SheetContent className="w-[80%] sm:w-[350px]">
                <SheetHeader>
                  <SheetTitle className="text-left">
                    <div className="flex items-center">
                      <Shield className="h-5 w-5 mr-2 text-navy-700" />
                      <span>MDD Management System</span>
                    </div>
                  </SheetTitle>
                </SheetHeader>
                
                <div className="mt-6 space-y-6">
                  {/* User info */}
                  <div className="border-b pb-6">
                    <div className="flex items-start space-x-4">
                      <Avatar className="h-10 w-10 border border-navy-100">
                        <AvatarFallback className="bg-navy-100 text-navy-800">
                          {getUserInitials()}
                        </AvatarFallback>
                      </Avatar>
                      
                      <div>
                        <div className="font-medium">{user?.fullName}</div>
                        <div className="text-sm text-muted-foreground">{user?.username}</div>
                        <div className="mt-1">{getRoleBadge()}</div>
                      </div>
                    </div>
                  </div>
                  
                  {/* Navigation */}
                  <nav className="space-y-2">
                    <Link href="/">
                      <a className="flex items-center py-2 px-3 rounded-md hover:bg-navy-50" onClick={closeMenu}>
                        Dashboard
                      </a>
                    </Link>
                    <Link href="/daily-status">
                      <a className="flex items-center py-2 px-3 rounded-md hover:bg-navy-50" onClick={closeMenu}>
                        Daily Status Reports
                      </a>
                    </Link>
                    <Link href="/employees">
                      <a className="flex items-center py-2 px-3 rounded-md hover:bg-navy-50" onClick={closeMenu}>
                        Employees
                      </a>
                    </Link>
                    <Link href="/history">
                      <a className="flex items-center py-2 px-3 rounded-md hover:bg-navy-50" onClick={closeMenu}>
                        History
                      </a>
                    </Link>
                    <Link href="/reports">
                      <a className="flex items-center py-2 px-3 rounded-md hover:bg-navy-50" onClick={closeMenu}>
                        Reports
                      </a>
                    </Link>
                  </nav>
                  
                  {/* Logout button */}
                  <div className="pt-6 border-t">
                    <Button
                      variant="outline"
                      className="w-full flex items-center justify-center"
                      onClick={handleLogout}
                    >
                      <LogOut className="h-4 w-4 mr-2" />
                      Logout
                    </Button>
                  </div>
                </div>
              </SheetContent>
            </Sheet>
          )}
        </div>
      </div>
    </header>
  );
}