import { Link } from "@inertiajs/react";
import { IconChartBar, IconPlus } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";

export function InvoicesPrimaryButtons() {
  return (
    <div className="flex gap-2">
      <Button variant="outline" asChild>
        <Link href={route("dashboard.invoices.reports")}>
          <IconChartBar size={18} className="mr-1" /> Reports
        </Link>
      </Button>
      <Button asChild>
        <Link href={route("dashboard.invoices.create")}>
          <IconPlus size={18} className="mr-1" /> New Invoice
        </Link>
      </Button>
    </div>
  );
}
