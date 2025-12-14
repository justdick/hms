# Drugs Missing Bottle Sizes

These drugs have `unit_type = 'bottle'` but no `bottle_size` defined. Please verify and fill in the correct sizes.

## Instructions
1. Review each drug and fill in the correct bottle size (in ml)
2. Once verified, we'll create an update script to apply these values

---

| ID | Drug Name | Form | Current Strength | Suggested Size (ml) | Verified Size (ml) |
|----|-----------|------|------------------|---------------------|-------------------|
| 39 | Amoxicillin + Clavulanic Acid Suspension, 250 mg + | suspension | 250 mg | 100 | |
| 40 | Amoxicillin + Clavulanic Acid Suspension, 400 mg + | suspension | 400 mg | 100 | |
| 50 | Artemether + Lumefantrine Suspension, (Powder For | suspension | - | 60 | |
| 370 | Metronidazole Suspension, 100 mg/5 mL (as | suspension | 100 mg/5 mL | 100 | |
| 371 | Metronidazole Suspension, 200 mg/5 mL(as | suspension | 200 mg/5 mL | 100 | |
| 556 | neohycolex eye/ear drop | drops | 1.00 | 10 | |
| 558 | NEOHYCOLEX | syrup | 1.00 | 10 | |
| 559 | CIPROFLOXACIN EYE/EAR DROP | drops | 1.00 | 10 | |
| 560 | CIPROFLOXACIN EYE/EAR DROP (duplicate) | drops | 1.00 | 10 | |
| 568 | NEVIRAPINE 50MG/5ML | syrup | 50.00 | 240 | |
| 570 | ZIDOVUDINE 50MG/5ML | syrup | 50.00 | 240 | |
| 571 | ABACAVIR/LAMIVUDINE, 120MG/60MG | syrup | 120.00 | 240 | |
| 581 | MIST. EXPECTORANT SEDATIVE | syrup | 1.00 | 100 | |
| 608 | NUGEL-O SYRUP | syrup | 1.00 | 100 | |
| 609 | NUGEL SYRUP | syrup | 1.00 | 100 | |
| 627 | septrin | syrup | 240.00 | 100 | |
| 628 | boric acid | syrup | 10.00 | 100 | |
| 629 | glycerie spirit | syrup | 10.00 | 100 | |
| 630 | irovit drop | drops | 30.00 | 30 | |
| 653 | CEFOMAX 125 | syrup | 10.00 | 100 | |

---

## Notes

### Data Quality Issues
1. **Duplicates**: 
   - ID 559 & 560: Both are "CIPROFLOXACIN EYE/EAR DROP"
   - ID 556 & 558: Both appear to be Neohycolex

2. **Truncated names**: Some names appear cut off (ending with "+", "(as", etc.)

### Form Classifications Fixed
The following drugs had incorrect form classifications which have been fixed by `php artisan migrate:fix-drug-forms`:
- ID 552: Paracetamol Injection → form changed to `injection`, unit_type to `vial`
- ID 556, 559, 560, 630: Eye/ear drops → form changed to `drops`
- ID 611: Propofol Injection → form changed to `injection`, unit_type to `vial`

### Common Bottle Sizes Reference
- Pediatric suspensions: 60ml, 100ml
- Adult syrups: 100ml, 200ml
- Eye/ear drops: 5ml, 10ml
- ARV syrups: 240ml
- Iron drops: 30ml
- Injection vials: varies (1ml, 2ml, 10ml, 20ml, 100ml)
