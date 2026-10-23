import unittest,tempfile,subprocess,json,pathlib,sys
import openpyxl
class FinancialBlockExtraction(unittest.TestCase):
 def test_allocation_target_is_not_mistaken_for_invoice_and_zero_is_preserved(self):
  with tempfile.TemporaryDirectory() as tmp:
   tmp=pathlib.Path(tmp);w=openpyxl.Workbook();w.remove(w.active)
   for name in ['2026 ALFAH BARAKAH Pipeline','2026 Nicolette Nana Ekua Pipeli','2026 MASANA ALFAH  Pipeline','2026 JOVITA ANANKUM pipeline']:w.create_sheet(name)
   s=w.worksheets[0];s.append(['BRAND','PROJECT','PO RAISED','PO BALANCE','STATUS','INVOICED','INVOICE STATUS']);s.append(['Dove','Event',100,0,'Issued',100,'GRed']);s.append(['NO.','BRANDS/ACCOUNT','CONTACT PERSON','CONTACT POSITION','CONTACT INFO','YTD TARGET','MONTHLY TARGET']);s.append([1,'Dove','Not financial',None,None,6000000,1200000]);w.save(tmp/'source.xlsx')
   subprocess.run([sys.executable,'scripts/extract_crm_financial_register.py',str(tmp/'source.xlsx'),str(tmp/'out.json')],check=True,capture_output=True)
   rows=json.loads((tmp/'out.json').read_text())['rows'];self.assertEqual(len(rows),1);self.assertEqual(rows[0]['invoiced_amount'],100);self.assertEqual(rows[0]['po_balance'],0);self.assertIsNone(rows[0]['as_of'])
if __name__=='__main__':unittest.main()
