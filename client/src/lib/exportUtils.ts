import { saveAs } from 'file-saver';
import { jsPDF } from 'jspdf';
import 'jspdf-autotable';
import * as XLSX from 'xlsx';
import logoPng from "@assets/logo.jpg";

// Helper to format date strings
export const formatDate = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-UG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

// Type for document headers
interface DocumentHeaders {
  title: string;
  subtitle?: string;
  date?: string;
  additionalInfo?: string;
}

// Convert base64 image
const getBase64Image = (imgUrl: string): Promise<string> => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'Anonymous';
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = img.width;
      canvas.height = img.height;
      const ctx = canvas.getContext('2d');
      ctx?.drawImage(img, 0, 0);
      const dataURL = canvas.toDataURL('image/jpeg');
      resolve(dataURL);
    };
    img.onerror = error => reject(error);
    img.src = imgUrl;
  });
};

// Export to CSV
export const exportToCSV = (
  data: any[],
  headers: { key: string; label: string }[],
  filename: string
) => {
  const csvData = data.map(row => {
    return headers.map(header => {
      const value = row[header.key];
      // Handle nested objects
      if (typeof value === 'object' && value !== null) {
        return JSON.stringify(value);
      }
      return value;
    }).join(',');
  });
  
  // Add header row
  const headerRow = headers.map(h => h.label).join(',');
  csvData.unshift(headerRow);
  
  // Create blob and download
  const csvContent = csvData.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  saveAs(blob, `${filename}.csv`);
};

// Export to Excel
export const exportToExcel = (
  data: any[],
  headers: { key: string; label: string }[],
  filename: string,
  docHeaders: DocumentHeaders
) => {
  // Process data for Excel
  const excelData = data.map(row => {
    const newRow: Record<string, any> = {};
    headers.forEach(header => {
      newRow[header.label] = row[header.key];
    });
    return newRow;
  });
  
  // Create workbook and worksheet
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.json_to_sheet(excelData);
  
  // Add headers to the top
  let headerRows = 0;
  if (docHeaders.title) {
    XLSX.utils.sheet_add_aoa(ws, [[docHeaders.title]], { origin: -1 });
    headerRows++;
  }
  if (docHeaders.subtitle) {
    XLSX.utils.sheet_add_aoa(ws, [[docHeaders.subtitle]], { origin: -1 });
    headerRows++;
  }
  if (docHeaders.date) {
    XLSX.utils.sheet_add_aoa(ws, [[`Date: ${docHeaders.date}`]], { origin: -1 });
    headerRows++;
  }
  if (docHeaders.additionalInfo) {
    XLSX.utils.sheet_add_aoa(ws, [[docHeaders.additionalInfo]], { origin: -1 });
    headerRows++;
  }
  
  // Add blank row after headers
  XLSX.utils.sheet_add_aoa(ws, [['']], { origin: -1 });
  headerRows++;
  
  // Append the data
  XLSX.utils.sheet_add_json(ws, excelData, { origin: headerRows });
  
  // Add worksheet to workbook
  XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
  
  // Generate Excel file
  XLSX.writeFile(wb, `${filename}.xlsx`);
};

// Export to PDF
export const exportToPDF = async (
  data: any[],
  headers: { key: string; label: string }[],
  filename: string,
  docHeaders: DocumentHeaders
) => {
  // Create PDF document
  const doc = new jsPDF();
  
  try {
    // Add logo
    const logoBase64 = await getBase64Image(logoPng);
    doc.addImage(logoBase64, 'JPEG', 15, 10, 20, 20);
    
    // Add headers
    doc.setFontSize(16);
    doc.text('UGANDA POLICE FORCE', 105, 20, { align: 'center' });
    doc.setFontSize(12);
    doc.text('MDD MANAGEMENT SYSTEM', 105, 27, { align: 'center' });
    doc.setFontSize(10);
    doc.text('PROTECT & SERVE', 105, 32, { align: 'center' });
    
    // Add document title
    doc.setFontSize(14);
    doc.text(docHeaders.title, 105, 40, { align: 'center' });
    
    // Add subtitle if available
    if (docHeaders.subtitle) {
      doc.setFontSize(12);
      doc.text(docHeaders.subtitle, 105, 47, { align: 'center' });
    }
    
    // Add date if available
    if (docHeaders.date) {
      doc.setFontSize(10);
      doc.text(`Date: ${docHeaders.date}`, 105, 54, { align: 'center' });
    }
    
    // Add additional info if available
    if (docHeaders.additionalInfo) {
      doc.setFontSize(10);
      doc.text(docHeaders.additionalInfo, 105, 60, { align: 'center' });
    }
    
    // Prepare table data
    const tableData = data.map(row => {
      return headers.map(header => {
        const value = row[header.key];
        // Handle nested objects
        if (typeof value === 'object' && value !== null) {
          try {
            return JSON.stringify(value);
          } catch (e) {
            return 'Complex Object';
          }
        }
        return value !== undefined && value !== null ? value.toString() : '';
      });
    });
    
    // Create table
    (doc as any).autoTable({
      head: [headers.map(h => h.label)],
      body: tableData,
      startY: 65,
      theme: 'grid',
      styles: {
        fontSize: 9,
        cellPadding: 3,
      },
      headStyles: {
        fillColor: [13, 22, 41], // Navy-900 color
        textColor: [255, 255, 255],
        fontStyle: 'bold',
      },
    });
    
    // Add footer
    const pageCount = (doc as any).internal.getNumberOfPages();
    doc.setFontSize(8);
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.text(
        `Generated on ${new Date().toLocaleString()} - Page ${i} of ${pageCount}`,
        105,
        doc.internal.pageSize.height - 10,
        { align: 'center' }
      );
    }
    
    // Save the PDF
    doc.save(`${filename}.pdf`);
  } catch (error) {
    console.error('Error exporting to PDF:', error);
  }
};

// Main export function
export const exportData = async (
  data: any[],
  headers: { key: string; label: string }[],
  filename: string,
  format: 'csv' | 'excel' | 'pdf',
  docHeaders: DocumentHeaders
) => {
  switch (format) {
    case 'csv':
      exportToCSV(data, headers, filename);
      break;
    case 'excel':
      exportToExcel(data, headers, filename, docHeaders);
      break;
    case 'pdf':
      await exportToPDF(data, headers, filename, docHeaders);
      break;
    default:
      console.error('Unsupported export format');
  }
};