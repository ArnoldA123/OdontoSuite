// One slug for every audit artefact, shared by the browser harness, the static
// pass and the report: the render evidence file name and the static row must
// name the same surface. `harness.js` re-exports it, so the spec keeps one
// import surface.
//
// `/patients/:id` -> `patients-id`, `/` -> `root`,
// `/:pathMatch(.*)*` -> `pathmatch`, `/settings/branches/:id` -> `settings-branches-id`
export const slugFor = route => {
  const slug = route
    .replace(/\([^)]*\)/g, ' ')
    .replace(/:/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

  return slug || 'root'
}

export default slugFor
