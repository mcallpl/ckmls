---
name: Radius drag behavior is perfect - do not change
description: User loves the draggable radius circle that live-filters properties as you resize. Never modify this behavior.
type: feedback
---

The draggable radius circle on the map works exactly as the user wants. As you make it smaller, homes disappear. As you make it bigger, homes appear. This behavior is confirmed perfect.

**Why:** User explicitly said "I love that. Do not change that at all."

**How to apply:** When working on map features, never alter the editable circle, radius_changed listener, or filterByCurrentSpatialMode() for radius mode. Only add new features alongside it.
