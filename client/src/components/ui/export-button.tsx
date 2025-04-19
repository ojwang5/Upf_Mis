import { useState } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { Download, FileSpreadsheet, FileText, FileType } from "lucide-react";
import { exportData } from "@/lib/exportUtils";

export interface ExportButtonProps {
  data: any[];
  headers: { key: string; label: string }[];
  filename: string;
  title: string;
  subtitle?: string;
  date?: string;
  additionalInfo?: string;
  className?: string;
}

export function ExportButton({
  data,
  headers,
  filename,
  title,
  subtitle,
  date,
  additionalInfo,
  className,
}: ExportButtonProps) {
  const [isExporting, setIsExporting] = useState(false);

  const handleExport = async (format: 'csv' | 'excel' | 'pdf') => {
    try {
      setIsExporting(true);
      await exportData(
        data, 
        headers, 
        filename, 
        format, 
        {
          title,
          subtitle,
          date,
          additionalInfo,
        }
      );
    } catch (error) {
      console.error(`Error exporting as ${format}:`, error);
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button 
          variant="outline" 
          className={className}
          disabled={isExporting || data.length === 0}
        >
          <Download className="h-4 w-4 mr-2" />
          Export
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem 
          onClick={() => handleExport('pdf')}
          className="cursor-pointer"
        >
          <FileText className="h-4 w-4 mr-2" />
          <span>PDF Document</span>
        </DropdownMenuItem>
        <DropdownMenuItem 
          onClick={() => handleExport('excel')}
          className="cursor-pointer"
        >
          <FileSpreadsheet className="h-4 w-4 mr-2" />
          <span>Excel Spreadsheet</span>
        </DropdownMenuItem>
        <DropdownMenuItem 
          onClick={() => handleExport('csv')}
          className="cursor-pointer"
        >
          <FileType className="h-4 w-4 mr-2" />
          <span>CSV File</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}