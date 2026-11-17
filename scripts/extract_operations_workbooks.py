"""Extract three read-only workbook snapshots. Requires openpyxl; no portal writes."""
import argparse
import datetime as dt
import hashlib
import json
import re
from pathlib import Path

import openpyxl

WORKBOOKS = {
    "fabrication": ("1pIr9FsHW_8Gc5oj4rW5gQ9JiDrb5zreq", "Fabrication Activity"),
    "allocation": ("15ey3zAb1s344iICmO5EPcQamvc_2tyUZ", "Project Allocation"),
    "tracker": ("1VbSyYlzUNXVMeoUHVSR8w6SGuKyOz87Y", "Activation Tracker Main"),
}
MONTHS = {name: i for i, name in enumerate(
    "jan feb mar apr may jun jul aug sep oct nov dec".split(), 1)}
UNDATED = "0001-01-01"
ACTIVATION_FIELDS = dict(zip("BDEGHJN", (
    "brand", "location", "outlet", "starts_on", "ends_on", "source_project_lead", "remarks")))
POSM_FIELDS = dict(zip("BDEFGHJKLMNOPQST", (
    "brand", "category", "asset_name", "quantity", "location", "outlet",
    "deployed_on", "returned_on", "source_project_lead", "deployed_by", "received_by",
    "condition_before", "deployment_status", "return_status", "current_location", "remarks")))
FAB_FIELDS = dict(zip("ACDEFGHIJKLMO", (
    "source_serial", "description", "dimensions", "materials", "quantity", "source_project_lead",
    "vendor", "artwork_shared", "produced_in", "starts_on", "due_on", "delivered_on", "remarks")))
STATUS = {"completed": "completed", "ongoing": "in_progress", "in progress": "in_progress",
          "upcoming": "planned", "on hold": "on_hold", "cancelled": "cancelled", "not started": "planned"}


def serial(value):
    if isinstance(value, dt.datetime):
        return value.date().isoformat() if value.time() == dt.time() else value.isoformat()
    if isinstance(value, (dt.date, dt.time)):
        return value.isoformat()
    if isinstance(value, float) and value.is_integer():
        return int(value)
    return value


def named_dates(text, year):
    """Resolve 13th to 15th Nov and 31st Oct to 1st Nov with the documented allocation year."""
    if not isinstance(text, str):
        return [serial(text)] if isinstance(text, (dt.date, dt.datetime)) else []
    parts = re.findall(r"(\d{1,2})(?:st|nd|rd|th)?\s*([A-Za-z]{3,9})?", text.lower())
    explicit = [MONTHS.get(month[:3]) for _, month in parts]
    if not any(explicit):
        return []
    dates = []
    for i, (day, _) in enumerate(parts):
        month = explicit[i] or next((m for m in explicit[i+1:] if m), None) or next(m for m in explicit if m)
        dates.append(dt.date(year, month, int(day)).isoformat())
    return dates


def extract(source, year):
    archive, records, inventory = [], [], {}
    for key, (drive_id, label) in WORKBOOKS.items():
        path = source / (drive_id + ".xlsx")
        book = openpyxl.load_workbook(path)
        cached = openpyxl.load_workbook(path, data_only=True)
        url = f"https://docs.google.com/spreadsheets/d/{drive_id}/edit"
        inventory[key] = {"sha256": hashlib.sha256(path.read_bytes()).hexdigest(), "tabs": {}}
        for sheet in book:
            rows = []
            for row in sheet.iter_rows():
                cells = []
                for cell in row:
                    if cell.value is None and not cell.comment and not cell.hyperlink:
                        continue
                    item = {"address": cell.coordinate, "value": serial(cell.value)}
                    if cell.data_type == "f":
                        item["cached"] = serial(cached[sheet.title][cell.coordinate].value)
                    if cell.comment:
                        item["note"] = cell.comment.text
                    if cell.hyperlink:
                        item["link"] = cell.hyperlink.target or cell.hyperlink.location
                    cells.append(item)
                if cells:
                    rows.append({"number": row[0].row, "cells": cells})
            archive.append({"workbook": label, "sheet": sheet.title, "url": url,
                            "hidden": sheet.sheet_state != "visible", "rows": rows})
            inventory[key]["tabs"][sheet.title] = {"rows": len(rows), "cells": sum(len(row["cells"]) for row in rows),
                                                  "records": 0, "charts": len(sheet._charts)}
            def val(ref):
                value = serial(sheet[ref].value)
                return value.strip() if isinstance(value, str) else value
            def fields(rowno, mapping):
                return {k: val(c + str(rowno)) for c, k in mapping.items() if val(c + str(rowno)) not in (None, "")}
            def add(kind, i, title, d, dates, lead=None, supporting=None, status=None, client=None, project=None, token=None):
                date = dates[0] if dates else UNDATED
                ref = f"{label} / {sheet.title} / row {i}"
                stable = hashlib.sha256(f"{drive_id}/{sheet.title}/{token or i}".encode()).hexdigest()[:24]
                d |= {"import_source": ref}
                if not dates:
                    d["import_undated"] = True
                records.append(dict(kind=kind, reference="OPS-XLSX-"+stable, title=title, client=client,
                                    project=project, period_start=date, period_end=max(dates) if dates else date,
                                    status=STATUS.get(str(status).lower(), "unrecorded"), data=d,
                                    lead=lead, supporting=supporting or []))
                inventory[key]["tabs"][sheet.title]["records"] += 1
            for row in rows:
                i = row["number"]
                if sheet.title == "Activation Tracker" and i >= 3 and val(f"F{i}"):
                    d = fields(i, ACTIVATION_FIELDS)
                    dates = [d[k] for k in ("starts_on", "ends_on") if k in d]
                    if val(f"K{i}"):
                        d["import_report_shared"] = str(val(f"K{i}")).lower() == "yes"
                    if isinstance(val(f"I{i}"), (int, float)):
                        d["duration_as_reported"] = val(f"I{i}")
                    d["source_serial"] = str(val(f"A{i}"))
                    explicit = None if sheet[f"M{i}"].data_type == "f" else val(f"M{i}")
                    if explicit:
                        d["activation_status"] = explicit
                    add("activation", i, val(f"F{i}"), d, dates, d.get("source_project_lead"),
                        status=explicit, client=val(f"C{i}"), token=val(f"A{i}"))
                elif sheet.title == "POSM Deployment" and i >= 2 and any(val(f"{col}{i}") not in (None, "") for col in POSM_FIELDS):
                    d = fields(i, POSM_FIELDS)
                    d["source_serial"] = str(val(f"A{i}"))
                    dates = [d["deployed_on"]] if d.get("deployed_on") else []
                    add("posm_deployment", i, val(f"E{i}") or f"{d.get('brand', 'POSM')} — item not recorded", d, dates, d.get("source_project_lead"),
                        client=val(f"C{i}"), project=val(f"I{i}"), token=val(f"A{i}"))
                elif key == "allocation" and i >= 4 and val(f"B{i}"):
                    have_outlet = (val("C3") or "").lower() == "outlet name"
                    cols = "ACDEFGHIJK" if have_outlet else "ACDEFGHIJ"
                    keys = ["source_serial", *(["outlet"] if have_outlet else []), "location", "client_service",
                            "project_in_charge", "setup_date_as_reported", "technical_support", "handyman",
                            "activation_dates_as_reported", "transport"]
                    d = fields(i, dict(zip(cols, keys, strict=True)))
                    if "source_serial" in d:
                        d["source_serial"] = str(d["source_serial"])
                    dates = named_dates(d.get("activation_dates_as_reported"), year)
                    setup = named_dates(d.get("setup_date_as_reported"), year)
                    if setup:
                        d["setup_on"] = setup[0]
                    if not dates:
                        raise ValueError(f"Cannot place allocation from {sheet.title} row {i}")
                    lead_parts = re.split(r"\s*[/&]\s*", d.get("project_in_charge", ""))
                    add("team_allocation", i, val(f"B{i}"), d, dates, lead_parts[0],
                        [*lead_parts[1:], d.get("client_service"), d.get("technical_support"), d.get("handyman")])
                elif key == "fabrication" and i >= 7 and val(f"B{i}"):
                    d = fields(i, FAB_FIELDS)
                    d["source_serial"] = str(d["source_serial"])
                    d |= {"brand": val("B2"), "project_manager": val("F3"),
                          "location": val("I2"), "prepared_on": val("I3")}
                    date = d.get("starts_on") or d["prepared_on"]
                    add("fabrication", i, val(f"B{i}"), d, [date, d.get("due_on") or date], d.get("source_project_lead"),
                        status=val(f"N{i}"), client=val("F2"), project=val("B2"))
    assert len({r["reference"] for r in records}) == len(records)
    return {"archive": archive, "records": records, "inventory": inventory}


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("snapshot_dir", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--allocation-year", required=True, type=int)
    args = parser.parse_args()
    payload = extract(args.snapshot_dir, args.allocation_year)
    with args.output.open("w") as fd:
        json.dump(payload, fd, ensure_ascii=False, allow_nan=False, separators=(",", ":"))
