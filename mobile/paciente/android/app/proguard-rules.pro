# Didit SDK 3.3.x referencia com.google.android.material.datepisker.PickerFragment (typo en el AAR).
# R8 genera esta regla en build/app/outputs/mapping/release/missing_rules.txt
-dontwarn com.google.android.material.datepisker.PickerFragment

# Material Components (datepicker usado por Didit)
-dontwarn com.google.android.material.datepicker.**

-keep class ai.didit.** { *; }
