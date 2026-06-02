/* Poolhall UI kit — Jobs listing with filters */

function FilterGroup({ title, children }) {
  return (
    <div style={{ borderBottom: "1px solid var(--mist)", padding: "16px 0" }}>
      <div style={{ fontWeight: 700, fontSize: 13, color: "var(--fg-1)", marginBottom: 12, letterSpacing: ".02em" }}>{title}</div>
      {children}
    </div>
  );
}

function Check({ label, count, checked, onChange }) {
  return (
    <label style={{ display: "flex", alignItems: "center", gap: 9, padding: "6px 0", fontSize: 14, color: "var(--fg-2)", cursor: "pointer" }}>
      <input type="checkbox" checked={checked} onChange={onChange} style={{ width: 16, height: 16, accentColor: "var(--orange-500)" }} />
      <span style={{ flex: 1 }}>{label}</span>
      {count != null && <span style={{ fontSize: 12, color: "var(--fg-3)" }}>{count}</span>}
    </label>
  );
}

function Jobs({ go }) {
  const D = window.PH_DATA;
  const [sector, setSector] = useState(null);
  const [sort, setSort] = useState("recent");
  const list = D.jobs.filter(j => !sector || j.sector === sector);

  return (
    <main className="bg-paper" style={{ minHeight: "60vh" }}>
      <div className="container">
        <div className="crumb">
          <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a>
          <Icon name="chevron-right" /> <span>Find a Job</span>
        </div>
      </div>

      {/* search strip */}
      <div className="bg-white" style={{ borderTop: "1px solid var(--border)", borderBottom: "1px solid var(--border)", padding: "22px 0" }}>
        <div className="container">
          <div className="searchbar" style={{ marginTop: 0, boxShadow: "var(--shadow-sm)" }}>
            <div className="field"><Icon name="search" /><input placeholder="Job title or keyword" defaultValue="" /></div>
            <div className="field"><Icon name="map-pin" /><input placeholder="Location" /></div>
            <div className="field"><Icon name="layers" />
              <select value={sector || ""} onChange={e => setSector(e.target.value || null)}>
                <option value="">All sectors</option>
                {D.sectors.map(s => <option key={s.name}>{s.name}</option>)}
              </select>
            </div>
            <Button variant="primary" size="lg" icon="search">Search</Button>
          </div>
        </div>
      </div>

      <div className="container" style={{ padding: "40px 24px" }}>
        <div style={{ display: "grid", gridTemplateColumns: "280px 1fr", gap: 40, alignItems: "start" }}>
          {/* filters */}
          <aside style={{ background: "#fff", border: "1px solid var(--border)", borderRadius: "var(--r-lg)", padding: "8px 22px 22px", boxShadow: "var(--shadow-sm)", position: "sticky", top: 100 }}>
            <FilterGroup title="Sector">
              <Check label="All sectors" checked={!sector} onChange={() => setSector(null)} />
              {D.sectors.map(s => <Check key={s.name} label={s.name} count={s.count} checked={sector === s.name} onChange={() => setSector(s.name)} />)}
            </FilterGroup>
            <FilterGroup title="Job type">
              <Check label="Permanent" count={6} checked={true} onChange={() => {}} />
              <Check label="Temporary" count={0} checked={false} onChange={() => {}} />
              <Check label="Contract" count={0} checked={false} onChange={() => {}} />
            </FilterGroup>
            <FilterGroup title="Working pattern">
              <Check label="Must be onsite" count={4} checked={false} onChange={() => {}} />
              <Check label="Part remote / hybrid" count={2} checked={false} onChange={() => {}} />
              <Check label="Fully remote" count={0} checked={false} onChange={() => {}} />
            </FilterGroup>
            <FilterGroup title="Salary (min)">
              <input type="range" min="20000" max="80000" step="5000" defaultValue="30000" style={{ width: "100%", accentColor: "var(--orange-500)" }} />
              <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "var(--fg-3)", marginTop: 4 }}><span>£20k</span><span>£80k+</span></div>
            </FilterGroup>
            <Button variant="ghost" className="btn-block" style={{ marginTop: 18 }} onClick={() => setSector(null)}>Clear filters</Button>
          </aside>

          {/* results */}
          <section>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 16, marginBottom: 20, flexWrap: "wrap" }}>
              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <h1 className="h2" style={{ fontSize: "1.6rem" }}>{sector || "All roles"}</h1>
                <p className="ph-meta" style={{ marginTop: 6 }}>{list.length} jobs found{sector ? " in " + sector : ""}</p>
              </div>
              <div className="field" style={{ position: "relative", flex: "0 0 auto" }}>
                <select value={sort} onChange={e => setSort(e.target.value)}
                  style={{ appearance: "none", padding: "10px 36px 10px 14px", border: "1.5px solid var(--border-strong)", borderRadius: "var(--r-sm)", fontFamily: "var(--font-body)", fontWeight: 600, fontSize: 14, color: "var(--fg-1)", background: "#fff" }}>
                  <option value="recent">Most recent</option>
                  <option value="salary">Highest salary</option>
                  <option value="az">A–Z</option>
                </select>
                <Icon name="chevron-down" style={{ position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)", pointerEvents: "none", color: "var(--slate-400)" }} />
              </div>
            </div>
            <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
              {list.map(j => <JobCard key={j.id} job={j} go={go} />)}
            </div>
            <div style={{ display: "flex", justifyContent: "center", gap: 8, marginTop: 36 }}>
              <button className="cbtn" aria-label="Previous"><Icon name="arrow-left" /></button>
              {[1, 2].map(n => (
                <button key={n} className="cbtn" style={n === 1 ? { background: "var(--navy-700)", color: "#fff", borderColor: "var(--navy-700)", fontWeight: 700 } : { fontWeight: 700 }}>{n}</button>
              ))}
              <button className="cbtn" aria-label="Next"><Icon name="arrow-right" /></button>
            </div>
          </section>
        </div>
      </div>
    </main>
  );
}

window.Jobs = Jobs;
