import { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import * as XLSX from "xlsx";
import { toast } from "sonner";
import { Download, FileSpreadsheet, Sheet } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Sheet as SlideOver,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";
import { FU_META, getSellerFuMeta, formatDate, nf, type Shipment } from "@/lib/posindo";

export function ExportPanel({
  open,
  onOpenChange,
  rows,
  seller,
}: {
  open: boolean;
  onOpenChange: (o: boolean) => void;
  rows: Shipment[];
  seller: string;
}) {
  const fuMeta = getSellerFuMeta(seller);
  const [scope, setScope] = useState<"all" | "fu">("all");

  const safeRows = useMemo(() => (Array.isArray(rows) ? rows : []), [rows]);
  const safeSeller = seller || "Semua Seller";

  const data = useMemo(
    () =>
      scope === "all"
        ? safeRows
        : safeRows.filter((r) => r && ["KUNING", "HIJAU", "BIRU_TUA", "PUTIH"].includes(r.fu)),
    [safeRows, scope],
  );

  const summary = useMemo(() => {
    const c = (f: string) => (data || []).filter((r) => r?.fu === f).length;
    return {
      total: data?.length || 0,
      sukses: c("BIRU"),
      retur: c("ORANGE"),
      fu: c("KUNING") + c("HIJAU") + c("BIRU_TUA"),
    };
  }, [data]);

  const download = () => {
    // Client side spreadsheet generator
    const aoa = [
      ["LAPORAN PENGIRIMAN POS INDONESIA — KANTOR POS CILACAP"],
      [`Seller: ${seller}`, "", `Total Resi: ${data.length}`],
      [],
      [
        "No",
        "Seller",
        "No. Resi",
        "Tgl Kirim",
        "Tujuan",
        "Penerima",
        "Keterangan",
        "Status NIPOS",
        "SLA (Hari)",
        "Status FU CS",
        "Catatan",
      ],
      ...data.map((r, i) => [
        i + 1,
        r.seller,
        r.resi,
        formatDate(r.tanggalKirim),
        r.tujuan,
        `${r.penerima} / ${r.telepon}`,
        r.keterangan,
        r.nipos,
        r.sla,
        fuMeta[r.fu]?.label || r.fu,
        r.note ?? "",
      ]),
    ];
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws["!cols"] = [
      { wch: 5 },
      { wch: 16 },
      { wch: 22 },
      { wch: 12 },
      { wch: 16 },
      { wch: 28 },
      { wch: 28 },
      { wch: 18 },
      { wch: 10 },
      { wch: 16 },
      { wch: 22 },
    ];
    data.forEach((r, i) => {
      const bg = (fuMeta[r.fu]?.bg || "#FFFFFF").replace("#", "");
      const fg = (fuMeta[r.fu]?.fg || "#000000").replace("#", "");
      for (let c = 0; c < 11; c++) {
        const ref = XLSX.utils.encode_cell({ r: i + 4, c });
        const cell = ws[ref];
        if (cell) cell.s = { fill: { fgColor: { rgb: bg } }, font: { color: { rgb: fg } } };
      }
    });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Laporan");
    XLSX.writeFile(wb, `Laporan-${seller.replace(/\s+/g, "-")}-Cilacap.xlsx`);

    // Trigger Laravel controller export colored excel
    router.post(
      "/export-colored-excel",
      { seller, scope },
      {
        onSuccess: () => {
          toast.success("Laporan Excel Berwarna Berhasil Diunduh!", {
            description: `${nf(data.length)} resi diekspor untuk ${seller}`,
          });
        },
      }
    );
  };

  return (
    <SlideOver open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-3xl">
        <SheetHeader className="border-b border-border bg-[#1E40AF] px-5 py-4 text-white">
          <SheetTitle className="flex items-center gap-2 text-white">
            <FileSpreadsheet className="h-5 w-5 text-[#F97316]" /> Preview Laporan Seller
          </SheetTitle>
          <SheetDescription className="text-blue-100">
            Pratinjau visual file Excel sebelum diunduh — {seller}
          </SheetDescription>
        </SheetHeader>

        <div className="flex items-center justify-between gap-4 border-b border-border bg-muted/40 px-5 py-3">
          <RadioGroup
            value={scope}
            onValueChange={(v) => setScope(v as "all" | "fu")}
            className="flex gap-5"
          >
            <div className="flex items-center gap-2">
              <RadioGroupItem value="all" id="sc-all" />
              <Label htmlFor="sc-all" className="text-xs font-semibold cursor-pointer">
                Export All Resi
              </Label>
            </div>
            <div className="flex items-center gap-2">
              <RadioGroupItem value="fu" id="sc-fu" />
              <Label htmlFor="sc-fu" className="text-xs font-semibold cursor-pointer">
                Export Only Need Follow-Up
              </Label>
            </div>
          </RadioGroup>
          <Button onClick={download} className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer">
            <Download className="h-4 w-4 text-[#F97316]" /> Download .XLSX Now
          </Button>
        </div>

        <div className="pos-scroll flex-1 overflow-auto bg-muted/30 p-5">
          <div className="rounded-lg border border-border bg-card shadow-sm">
            <div className="flex items-center gap-2 border-b border-border bg-[#1E40AF] px-4 py-3 text-white">
              <Sheet className="h-4 w-4 text-[#F97316]" />
              <div>
                <div className="text-sm font-bold">POS INDONESIA — KANTOR POS CILACAP</div>
                <div className="text-[11px] opacity-80">
                  Laporan Monitoring Kiriman &middot; {seller}
                </div>
              </div>
            </div>
            <div className="grid grid-cols-4 gap-px bg-border text-center">
              {[
                ["Total Resi", summary.total, "#F3F4F6", "#374151"],
                ["Sukses", summary.sukses, "#BAE6FD", "#0369A1"],
                ["Retur", summary.retur, "#FED7AA", "#C2410C"],
                ["Perlu FU", summary.fu, "#FEF08A", "#854D0E"],
              ].map(([l, v, bg, fg]) => (
                <div key={l as string} style={{ backgroundColor: bg as string }} className="p-2">
                  <div className="text-[10px] font-semibold" style={{ color: fg as string }}>
                    {l as string}
                  </div>
                  <div
                    className="text-base font-bold tabular-nums"
                    style={{ color: fg as string }}
                  >
                    {nf(v as number)}
                  </div>
                </div>
              ))}
            </div>
            <table className="w-full border-collapse text-[11px]">
              <thead>
                <tr className="bg-[#1E40AF] text-white [&>th]:border [&>th]:border-white/20 [&>th]:px-2 [&>th]:py-1.5 [&>th]:text-left">
                  <th>No</th>
                  <th>No. Resi</th>
                  <th>Tgl</th>
                  <th>Tujuan</th>
                  <th>Penerima</th>
                  <th>NIPOS</th>
                  <th>SLA</th>
                  <th>Status FU</th>
                </tr>
              </thead>
              <tbody>
                {data.slice(0, 60).map((r, i) => (
                  <tr
                    key={r.id}
                    style={{
                      backgroundColor: fuMeta[r.fu]?.bg || "#FFFFFF",
                      color: r.fu === "BIRU_TUA" ? "#FFFFFF" : "#111827",
                    }}
                    className="[&>td]:border [&>td]:border-black/10 [&>td]:px-2 [&>td]:py-1"
                  >
                    <td>{i + 1}</td>
                    <td className="font-mono">{r.resi}</td>
                    <td>{formatDate(r.tanggalKirim)}</td>
                    <td>{r.tujuan}</td>
                    <td>{r.penerima}</td>
                    <td>{r.nipos}</td>
                    <td>{r.sla}</td>
                    <td className="font-semibold">{fuMeta[r.fu]?.label || r.fu}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {data.length > 60 && (
              <div className="border-t border-border px-3 py-2 text-[11px] text-muted-foreground">
                Menampilkan 60 dari {nf(data.length)} baris pada pratinjau.
              </div>
            )}
          </div>
        </div>
      </SheetContent>
    </SlideOver>
  );
}
