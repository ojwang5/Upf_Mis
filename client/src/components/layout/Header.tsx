import { useAuth } from "@/hooks/use-auth";
import logoPng from "@assets/logo.jpg";
import { Button } from "@/components/ui/button";
import { LogOut } from "lucide-react";

export default function Header() {
  const { user, logoutMutation } = useAuth();
  
  const handleLogout = () => {
    logoutMutation.mutate();
  };
  
  return (
    <header className="bg-navy-900 text-white p-4 shadow-md">
      <div className="container mx-auto">
        <div className="flex flex-col md:flex-row items-center justify-between">
          <div className="flex items-center gap-4">
            <img 
              src={logoPng} 
              alt="Uganda Police Force Logo" 
              className="h-16 w-16 object-contain"
            />
            <div className="text-center md:text-left">
              <h1 className="text-xl md:text-2xl font-bold tracking-wider">UGANDA POLICE FORCE</h1>
              <h2 className="text-sm md:text-base text-slate-300">MDD MANAGEMENT SYSTEM</h2>
              <p className="text-xs text-slate-400 italic">PROTECT & SERVE</p>
            </div>
          </div>
          
          {user && (
            <div className="mt-4 md:mt-0 flex items-center gap-4">
              <div className="text-right">
                <p className="font-medium">{user.fullName}</p>
                <p className="text-xs text-slate-300">{user.role === 'admin' ? 'Administrator' : 'Branch Manager'}</p>
              </div>
              <Button 
                variant="outline" 
                size="sm" 
                className="border-white text-white hover:bg-white hover:text-navy-900"
                onClick={handleLogout}
              >
                <LogOut size={16} className="mr-2" />
                Logout
              </Button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}