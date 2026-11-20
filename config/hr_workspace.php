<?php
return json_decode(<<<'JSON'
{
  "tabs": {
    "people": {
      "label": "People & Staff Records",
      "group": "HR",
      "activities": [
        "Update staff file",
        "Request missing documents",
        "Contract renewal",
        "JD drafting / update",
        "Role redesignation"
      ]
    },
    "recruitment": {
      "label": "Recruitment & Onboarding",
      "group": "HR",
      "activities": [
        "Post job advert",
        "Shortlist",
        "Schedule interview",
        "Reference checks",
        "Issue offer / appointment letter",
        "Onboarding and orientation",
        "Probation review and confirmation"
      ]
    },
    "attendance": {
      "label": "Attendance & Leave",
      "group": "HR",
      "activities": [
        "Approve leave",
        "Follow up on lateness / absence",
        "Timesheet review"
      ]
    },
    "performance": {
      "label": "Performance & Training",
      "group": "HR",
      "activities": [
        "Set KPIs",
        "Issue appraisal forms",
        "Conduct appraisal review",
        "Training needs assessment",
        "Schedule training",
        "Performance improvement plan follow-up"
      ]
    },
    "relations": {
      "label": "Employee Relations & Discipline",
      "group": "HR",
      "activities": [
        "Issue query",
        "Disciplinary hearing",
        "Issue warning",
        "Investigate grievance",
        "Close case"
      ]
    },
    "welfare": {
      "label": "Welfare & Engagement",
      "group": "HR",
      "activities": [
        "Organise staff event",
        "Welfare visit / support",
        "Send celebration message",
        "Run staff survey"
      ]
    },
    "compliance": {
      "label": "Policies, Compliance & Exits",
      "group": "HR",
      "activities": [
        "Draft / review policy",
        "Circulate for acknowledgement",
        "Statutory compliance task",
        "Process resignation / termination",
        "Clearance and handover",
        "Exit interview"
      ]
    },
    "facilities": {
      "label": "Office & Facilities",
      "group": "Admin",
      "activities": [
        "Repair / maintenance request",
        "AC servicing",
        "Pay utility bill",
        "Restock supplies",
        "Cleaning inspection"
      ]
    },
    "fleet": {
      "label": "Fleet & Drivers",
      "group": "Admin",
      "activities": [
        "Vehicle servicing",
        "Repair",
        "Renew insurance",
        "Renew roadworthy",
        "Fuel request",
        "Assign vehicle / driver"
      ]
    },
    "assets": {
      "label": "Assets & Equipment",
      "group": "Admin",
      "activities": [
        "Issue asset to staff",
        "Recover asset on exit",
        "Repair",
        "Dispose / write off",
        "Asset audit / stock count"
      ]
    },
    "supplies": {
      "label": "Supplies, Vendors & Communications",
      "group": "Admin",
      "activities": [
        "Purchase request",
        "Vendor follow-up",
        "Renew vendor contract",
        "Issue memo / notice",
        "Schedule meeting and record minutes"
      ]
    }
  },
  "registers": {
    "staff_document": {
      "tab": "people",
      "label": "Staff Documents",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "document_type": {
          "type": "select",
          "label": "Document type",
          "options": [
            "CV",
            "Referees",
            "ID",
            "Certificates",
            "Signed contract",
            "Signed JD",
            "Handbook acknowledgement"
          ]
        },
        "received_on": {
          "type": "date",
          "label": "Received on"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "vacancy": {
      "tab": "recruitment",
      "label": "Vacancies",
      "fields": {
        "role": {
          "type": "text",
          "label": "Role"
        },
        "department": {
          "type": "department",
          "label": "Department"
        },
        "opened_on": {
          "type": "date",
          "label": "Opened on"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Open",
            "On hold",
            "Filled",
            "Cancelled"
          ]
        },
        "target_fill_on": {
          "type": "date",
          "label": "Target fill on"
        },
        "filled_on": {
          "type": "date",
          "label": "Filled on"
        }
      }
    },
    "candidate": {
      "tab": "recruitment",
      "label": "Candidates",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "vacancy_id": {
          "type": "record=vacancy",
          "label": "Vacancy id"
        },
        "role_applied": {
          "type": "text",
          "label": "Role applied"
        },
        "stage": {
          "type": "select",
          "label": "Stage",
          "options": [
            "Applied",
            "Shortlisted",
            "Interview",
            "Reference check",
            "Offer",
            "Hired",
            "Declined",
            "Withdrawn"
          ]
        },
        "interview_at": {
          "type": "datetime-local",
          "label": "Interview at"
        },
        "outcome": {
          "type": "textarea",
          "label": "Outcome"
        }
      }
    },
    "probation": {
      "tab": "recruitment",
      "label": "Probation Register",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "starts_on": {
          "type": "date",
          "label": "Starts on"
        },
        "confirmation_due": {
          "type": "date",
          "label": "Confirmation due"
        },
        "confirmed_on": {
          "type": "date",
          "label": "Confirmed on"
        },
        "outcome": {
          "type": "textarea",
          "label": "Outcome"
        }
      }
    },
    "holiday": {
      "tab": "attendance",
      "label": "Public Holidays",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "date": {
          "type": "date",
          "label": "Date"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "appraisal_record": {
      "tab": "performance",
      "label": "Appraisal Register",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "period_start": {
          "type": "date",
          "label": "Period start"
        },
        "period_end": {
          "type": "date",
          "label": "Period end"
        },
        "due_on": {
          "type": "date",
          "label": "Due on"
        },
        "completed_on": {
          "type": "date",
          "label": "Completed on"
        },
        "score": {
          "type": "number",
          "label": "Score"
        },
        "rating": {
          "type": "text",
          "label": "Rating"
        },
        "appraiser_id": {
          "type": "staff",
          "label": "Appraiser id"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "training": {
      "tab": "performance",
      "label": "Training Log",
      "fields": {
        "title": {
          "type": "text",
          "label": "Title"
        },
        "date": {
          "type": "date",
          "label": "Date"
        },
        "facilitator": {
          "type": "text",
          "label": "Facilitator"
        },
        "attendees": {
          "type": "staff_multi",
          "label": "Attendees"
        },
        "required_completions": {
          "type": "number",
          "label": "Required completions"
        },
        "completed": {
          "type": "number",
          "label": "Completed"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "improvement_plan": {
      "tab": "performance",
      "label": "Improvement Plans",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "starts_on": {
          "type": "date",
          "label": "Starts on"
        },
        "due_on": {
          "type": "date",
          "label": "Due on"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Open",
            "Completed",
            "Cancelled"
          ]
        },
        "outcome": {
          "type": "textarea",
          "label": "Outcome"
        },
        "responsible_id": {
          "type": "staff",
          "label": "Responsible person"
        },
        "resolved_on": {
          "type": "date",
          "label": "Actual resolution date"
        }
      }
    },
    "employee_case": {
      "tab": "relations",
      "label": "Cases, Warnings & Grievances",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "case_type": {
          "type": "select",
          "label": "Case type",
          "options": [
            "Query",
            "Discipline",
            "Warning",
            "Grievance"
          ]
        },
        "opened_on": {
          "type": "date",
          "label": "Opened on"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Open",
            "Investigating",
            "Resolved",
            "Closed"
          ]
        },
        "summary": {
          "type": "textarea",
          "label": "Summary"
        },
        "outcome": {
          "type": "textarea",
          "label": "Outcome"
        },
        "responsible_id": {
          "type": "staff",
          "label": "Responsible person"
        },
        "due_on": {
          "type": "date",
          "label": "Follow-up deadline"
        },
        "resolved_on": {
          "type": "date",
          "label": "Actual resolution date"
        }
      }
    },
    "welfare_case": {
      "tab": "welfare",
      "label": "Welfare Cases",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "case_type": {
          "type": "select",
          "label": "Case type",
          "options": [
            "Bereavement",
            "Illness support",
            "Other"
          ]
        },
        "opened_on": {
          "type": "date",
          "label": "Opened on"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Open",
            "Closed"
          ]
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        },
        "responsible_id": {
          "type": "staff",
          "label": "Responsible person"
        },
        "due_on": {
          "type": "date",
          "label": "Follow-up deadline"
        },
        "resolved_on": {
          "type": "date",
          "label": "Actual resolution date"
        }
      }
    },
    "engagement": {
      "tab": "welfare",
      "label": "Engagement Events",
      "fields": {
        "title": {
          "type": "text",
          "label": "Title"
        },
        "date": {
          "type": "date",
          "label": "Date"
        },
        "attendees": {
          "type": "staff_multi",
          "label": "Attendees"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "feedback": {
      "tab": "welfare",
      "label": "Feedback & Surveys",
      "fields": {
        "title": {
          "type": "text",
          "label": "Title"
        },
        "date": {
          "type": "date",
          "label": "Date"
        },
        "responses": {
          "type": "number",
          "label": "Responses"
        },
        "summary": {
          "type": "textarea",
          "label": "Summary"
        }
      }
    },
    "policy": {
      "tab": "compliance",
      "label": "Policy Register",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "version": {
          "type": "text",
          "label": "Version"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Draft",
            "Approved",
            "Retired"
          ]
        },
        "approved_on": {
          "type": "date",
          "label": "Approved on"
        },
        "review_on": {
          "type": "date",
          "label": "Review on"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "acknowledgement": {
      "tab": "compliance",
      "label": "Policy Acknowledgements",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "policy_id": {
          "type": "record=policy",
          "label": "Policy id"
        },
        "signed_on": {
          "type": "date",
          "label": "Signed on"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "exit": {
      "tab": "compliance",
      "label": "Exit & Clearance Register",
      "fields": {
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "exit_type": {
          "type": "select",
          "label": "Exit type",
          "options": [
            "Resignation",
            "Termination",
            "Retirement",
            "Contract end",
            "Other"
          ]
        },
        "notice_on": {
          "type": "date",
          "label": "Notice on"
        },
        "last_day": {
          "type": "date",
          "label": "Last day"
        },
        "clearance_done": {
          "type": "checkbox",
          "label": "Clearance done"
        },
        "exit_interview_done": {
          "type": "checkbox",
          "label": "Exit interview done"
        },
        "completed_on": {
          "type": "date",
          "label": "Completed on"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "facility": {
      "tab": "facilities",
      "label": "Facilities Register",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "category": {
          "type": "select",
          "label": "Category",
          "options": [
            "AC",
            "Fan",
            "Fridge",
            "Water dispenser",
            "Printer",
            "Other"
          ]
        },
        "quantity": {
          "type": "number",
          "label": "Quantity"
        },
        "location": {
          "type": "text",
          "label": "Location"
        },
        "condition": {
          "type": "select",
          "label": "Condition",
          "options": [
            "Good",
            "Faulty",
            "Under repair",
            "Disposed"
          ]
        },
        "last_serviced": {
          "type": "date",
          "label": "Last serviced"
        },
        "service_due": {
          "type": "date",
          "label": "Service due"
        },
        "asset_id": {
          "type": "record=asset",
          "label": "Existing office asset (optional)"
        }
      }
    },
    "utility": {
      "tab": "facilities",
      "label": "Utilities Log",
      "fields": {
        "service": {
          "type": "select",
          "label": "Service",
          "options": [
            "Electricity",
            "Water",
            "Internet",
            "Other"
          ]
        },
        "provider": {
          "type": "text",
          "label": "Provider"
        },
        "account_reference": {
          "type": "text",
          "label": "Account reference"
        },
        "monthly_cost": {
          "type": "number",
          "label": "Monthly cost"
        },
        "due_on": {
          "type": "date",
          "label": "Due on"
        },
        "paid_on": {
          "type": "date",
          "label": "Paid on"
        }
      }
    },
    "cleaning": {
      "tab": "facilities",
      "label": "Cleaning & Sanitation",
      "fields": {
        "location": {
          "type": "text",
          "label": "Location"
        },
        "schedule": {
          "type": "text",
          "label": "Schedule"
        },
        "assigned_to": {
          "type": "staff",
          "label": "Assigned to"
        },
        "inspection_on": {
          "type": "date",
          "label": "Inspection on"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    },
    "admin_staff": {
      "tab": "facilities",
      "label": "Admin Support Staff",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "role": {
          "type": "select",
          "label": "Role",
          "options": [
            "Cleaner",
            "Security",
            "Handyman",
            "Other"
          ]
        },
        "shift": {
          "type": "text",
          "label": "Shift"
        },
        "phone": {
          "type": "text",
          "label": "Phone"
        }
      }
    },
    "vehicle": {
      "tab": "fleet",
      "label": "Vehicle Register",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "registration_number": {
          "type": "text",
          "label": "Registration number"
        },
        "vehicle_type": {
          "type": "text",
          "label": "Vehicle type"
        },
        "driver_id": {
          "type": "record=driver",
          "label": "Driver id"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Available",
            "In use",
            "Under maintenance",
            "Retired"
          ]
        },
        "mileage": {
          "type": "number",
          "label": "Mileage"
        },
        "insurance_expiry": {
          "type": "date",
          "label": "Insurance expiry"
        },
        "roadworthy_expiry": {
          "type": "date",
          "label": "Roadworthy expiry"
        },
        "last_serviced": {
          "type": "date",
          "label": "Last serviced"
        },
        "service_due": {
          "type": "date",
          "label": "Service due"
        },
        "service_due_mileage": {
          "type": "number",
          "label": "Service due mileage"
        }
      }
    },
    "driver": {
      "tab": "fleet",
      "label": "Driver Register",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "staff_id": {
          "type": "staff",
          "label": "Staff id"
        },
        "licence_number": {
          "type": "text",
          "label": "Licence number"
        },
        "licence_expiry": {
          "type": "date",
          "label": "Licence expiry"
        },
        "vehicle_id": {
          "type": "record=vehicle",
          "label": "Vehicle id"
        }
      }
    },
    "fuel": {
      "tab": "fleet",
      "label": "Fuel Log",
      "fields": {
        "date": {
          "type": "date",
          "label": "Date"
        },
        "vehicle_id": {
          "type": "record=vehicle",
          "label": "Vehicle id"
        },
        "litres": {
          "type": "number",
          "label": "Litres"
        },
        "amount": {
          "type": "number",
          "label": "Amount"
        },
        "approved_by": {
          "type": "staff",
          "label": "Approved by"
        }
      }
    },
    "trip": {
      "tab": "fleet",
      "label": "Trip & Usage Log",
      "fields": {
        "vehicle_id": {
          "type": "record=vehicle",
          "label": "Vehicle id"
        },
        "driver_id": {
          "type": "record=driver",
          "label": "Driver id"
        },
        "starts_at": {
          "type": "datetime-local",
          "label": "Starts at"
        },
        "ends_at": {
          "type": "datetime-local",
          "label": "Ends at"
        },
        "destination": {
          "type": "text",
          "label": "Destination"
        },
        "purpose": {
          "type": "textarea",
          "label": "Purpose"
        },
        "start_mileage": {
          "type": "number",
          "label": "Start mileage"
        },
        "end_mileage": {
          "type": "number",
          "label": "End mileage"
        }
      }
    },
    "vendor": {
      "tab": "supplies",
      "label": "Vendor Register",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "service": {
          "type": "text",
          "label": "Service"
        },
        "contact": {
          "type": "text",
          "label": "Contact"
        },
        "contract_end": {
          "type": "date",
          "label": "Contract end"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Active",
            "Inactive"
          ]
        }
      }
    },
    "supply": {
      "tab": "supplies",
      "label": "Stationery & Supplies",
      "fields": {
        "name": {
          "type": "text",
          "label": "Name"
        },
        "quantity": {
          "type": "number",
          "label": "Quantity"
        },
        "reorder_level": {
          "type": "number",
          "label": "Reorder level"
        },
        "location": {
          "type": "text",
          "label": "Location"
        }
      }
    },
    "purchase": {
      "tab": "supplies",
      "label": "Purchase Requests",
      "fields": {
        "item": {
          "type": "text",
          "label": "Item"
        },
        "quantity": {
          "type": "number",
          "label": "Quantity"
        },
        "requested_on": {
          "type": "date",
          "label": "Requested on"
        },
        "state": {
          "type": "select",
          "label": "State",
          "options": [
            "Pending",
            "Approved",
            "Ordered",
            "Received",
            "Cancelled"
          ]
        },
        "vendor_id": {
          "type": "record=vendor",
          "label": "Vendor id"
        },
        "amount": {
          "type": "number",
          "label": "Amount"
        }
      }
    },
    "communication": {
      "tab": "supplies",
      "label": "Memos, Letters & Minutes",
      "fields": {
        "title": {
          "type": "text",
          "label": "Title"
        },
        "document_type": {
          "type": "select",
          "label": "Document type",
          "options": [
            "Memo",
            "Letter",
            "Notice",
            "Minutes"
          ]
        },
        "date": {
          "type": "date",
          "label": "Date"
        },
        "audience": {
          "type": "text",
          "label": "Audience"
        },
        "notes": {
          "type": "textarea",
          "label": "Notes"
        }
      }
    }
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
