# Antrean Digital Bu Andika — proposal UI

Figma: https://www.figma.com/design/GOJY0SghUXI4vSI9ONw0e5/

Six editable screen compositions are saved in the supplied Figma file. Text remains native editable Figma text. Screens are static concepts for the proposal presentation, without app logic or linked prototype interactions. Imports use freeform layouts rather than responsive auto layout. Figma substituted Inter for the local Segoe UI source font.

## Images for PowerPoint

- `exports/customer-presentation.png`: customer registration and status side by side.
- `exports/customer-join.png`: registration, 780 × 1688, exported directly from Figma.
- `exports/customer-status.png`: tracking, 780 × 1688, exported directly from Figma.
- `exports/staff-dashboard.png`: staff dashboard, 2880 × 1800, exported directly from Figma.
- `exports/ui-overview.png`: combined overview using the three verified Figma exports.
- `exports/figma-preview.jpg`: screenshot of the completed dashboard in Figma.

The PowerPoint draft has not been modified.

## Draft design decisions

- Indonesian UI, teal and navy palette consistent with the presentation.
- Customer registration includes name, telephone, and party size, combining fields mentioned in different proposal sections. Confirm the final field requirements with the team.
- Estimated wait is illustrative, not a validated queue calculation.
- The called-customer state uses the proposed 15-minute arrival allowance. This business rule still needs validation.
- Staff confirm arrival; the customer page does not self-confirm check-in.
- All customer names, counts, and times are sample data.

The SVG, JSON, and build scripts are local design sources. `build-ui.mjs` regenerates local renderings and will overwrite PNGs, so re-export from Figma after making final edits there. `prepare-presentation-images.mjs` only composes the three Figma-exported PNGs into presentation images.
