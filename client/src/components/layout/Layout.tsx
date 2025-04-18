import { useState, ReactNode } from 'react';
import Header from './Header';
import Sidebar from './Sidebar';
import { useAuth } from '@/hooks/use-auth';

interface LayoutProps {
  children: ReactNode;
  title: string;
  description?: string;
}

export default function Layout({ children, title, description }: LayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const { user } = useAuth();

  const toggleSidebar = () => {
    setSidebarOpen(!sidebarOpen);
  };

  return (
    <div className="h-screen flex flex-col">
      <Header 
        toggleSidebar={toggleSidebar} 
        userName={user?.fullName || 'User'} 
        branchName={getBranchName(user?.branchAccess || '')}
      />
      
      <div className="flex flex-1 overflow-hidden">
        <Sidebar 
          isOpen={sidebarOpen} 
          closeSidebar={() => setSidebarOpen(false)} 
          userRole={user?.role || 'admin'}
          userName={user?.fullName || 'User'}
        />
        
        <main className="flex-1 overflow-y-auto bg-neutral-50 p-4 lg:p-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-condensed font-bold text-gray-800">{title}</h1>
              {description && <p className="text-gray-600">{description}</p>}
            </div>
          </div>
          
          {children}
        </main>
      </div>
    </div>
  );
}

function getBranchName(branchCode: string): string {
  switch(branchCode) {
    case 'kampala': return 'Kampala HQ';
    case 'rwizi': return 'Rwizi Mbarara';
    case 'nkyoga': return 'N.Kyoga Lira';
    default: return 'All Branches';
  }
}
