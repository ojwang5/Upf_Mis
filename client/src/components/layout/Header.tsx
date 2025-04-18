import { useState } from 'react';
import { useAuth } from '@/hooks/use-auth';
import { Menu, User, Settings, LogOut } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

interface HeaderProps {
  toggleSidebar: () => void;
  userName: string;
  branchName?: string;
}

export default function Header({ toggleSidebar, userName, branchName }: HeaderProps) {
  const { logoutMutation } = useAuth();
  
  const handleLogout = () => {
    logoutMutation.mutate();
  };
  
  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(part => part.charAt(0))
      .join('')
      .toUpperCase();
  };

  return (
    <header className="bg-blue-900 text-white shadow-md">
      <div className="container mx-auto flex justify-between items-center px-4 py-3">
        <div className="flex items-center">
          <button 
            onClick={toggleSidebar} 
            className="mr-4 lg:hidden focus:outline-none"
            aria-label="Toggle sidebar"
          >
            <Menu />
          </button>
          <h1 className="font-condensed text-xl font-bold">MDD MANAGER</h1>
        </div>
        
        <div className="flex items-center">
          {branchName && (
            <div className="mr-4 text-sm hidden md:block">
              <span className="opacity-80">Branch:</span> {branchName}
            </div>
          )}
          
          <DropdownMenu>
            <DropdownMenuTrigger className="focus:outline-none">
              <div className="flex items-center space-x-2">
                <span className="hidden sm:inline">{userName}</span>
                <Avatar className="h-8 w-8 bg-blue-800">
                  <AvatarFallback>{getInitials(userName)}</AvatarFallback>
                </Avatar>
              </div>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>
                <User className="mr-2 h-4 w-4" />
                <span>Profile</span>
              </DropdownMenuItem>
              <DropdownMenuItem>
                <Settings className="mr-2 h-4 w-4" />
                <span>Settings</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={handleLogout}>
                <LogOut className="mr-2 h-4 w-4" />
                <span>Logout</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}
