import { ReactNode } from 'react';

interface StatCardProps {
  title: string;
  value: number | string;
  icon: ReactNode;
  change?: string;
  color: 'present' | 'absent' | 'leave' | 'sick' | 'course';
}

export default function StatCard({ title, value, icon, change, color }: StatCardProps) {
  const colorVariants = {
    present: 'border-green-600',
    absent: 'border-red-600',
    leave: 'border-blue-600',
    sick: 'border-amber-600',
    course: 'border-purple-600'
  };
  
  const iconColors = {
    present: 'text-green-600',
    absent: 'text-red-600',
    leave: 'text-blue-600',
    sick: 'text-amber-600',
    course: 'text-purple-600'
  };
  
  const changeColors = {
    positive: 'text-green-600',
    negative: 'text-red-600',
    neutral: 'text-blue-600'
  };
  
  // Determine if the change is positive, negative, or neutral
  let changeType = 'neutral';
  if (change) {
    if (change.includes('+')) {
      changeType = 'positive';
    } else if (change.includes('-')) {
      changeType = 'negative';
    }
  }

  return (
    <div className={`bg-white rounded-lg shadow-sm p-4 border-l-4 ${colorVariants[color]}`}>
      <div className="flex justify-between items-start">
        <div>
          <p className="text-gray-600 text-sm">{title}</p>
          <h3 className="text-2xl font-bold text-gray-800 mt-1">{value}</h3>
          {change && (
            <p className="text-xs text-gray-600 mt-1">
              <span className={changeColors[changeType as keyof typeof changeColors]}>{change}</span> from yesterday
            </p>
          )}
        </div>
        <div className={`text-3xl ${iconColors[color]} opacity-80`}>
          {icon}
        </div>
      </div>
    </div>
  );
}
