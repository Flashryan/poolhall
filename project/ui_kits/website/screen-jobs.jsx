/* Poolhall UI kit v2 — Jobs listing (§9.3) */

function Jobs({ go }) {
  const D = window.PH_DATA;
  const [kw, setKw] = useState("");
  const [loc, setLoc] = useState("");
  const [sector, setSector] = useState("");
  const [work, setWork] = useState("");
  const [sort, setSort] = useState("recent");

  let list = D.jobs.filter(j =>
    (!sector || j.sector === sector) &&
    (!work || j.work === work) &&
    (!kw || (j.title + j.summary).toLowerCase().includes(kw.toLowerCase())) &&
    (!loc || (j.location + j.region).toLowerCase().includes(loc.toLowerCase()))
  );
  if (sort === "salary") list = list.slice().sort((a, b) => b.salaryMax - a.salaryMax);
  if (sort === "az") list = list.slice().sort((a, b) => a.title.localeCompare(b.title));
  else if (sort === "recent") list = list.slice().sort((a, b) => (b.featured ? 1 : 0) - (a.featured ? 1 : 0));

  const chips = [];
  if (kw) chips.push(["Keyword: " + kw, () => setKw("")]);
  if (loc) chips.push(["Location: " + loc, () => setLoc("")]);
  if (sector) chips.push([sector, () => setSector("")]);
  if (work) chips.push([work, () => setWork("")]);
  const clearAll = () => { setKw(""); setLoc(""); setSector(""); setWork(""); };

  return (
    <main className="bg-alt" style={{ minHeight: "70vh" }}>
      <div className="pagehead photo">
        <img src={window.PH_IMG.sectorMan} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <div className="crumb" style={{ color: "var(--steel-200)", paddingTop: 0 }}>
            <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a><Icon name="chevron-right" /><span>Jobs</span>
          </div>
          <h1>Live jobs</h1>
          <p className="lead">Every role here is a live vacancy we're actively recruiting. {D.jobs.length} open right now.</p>
        </div>
      </div>

      <div className="container" style={{ paddingTop: 28 }}>
        <div className="filterbar">
          <div className="fb-field"><Icon name="search" /><input placeholder="Job title or keyword" value={kw} onChange={e => setKw(e.target.value)} /></div>
          <div className="fb-field"><Icon name="map-pin" /><input placeholder="Location" value={loc} onChange={e => setLoc(e.target.value)} /></div>
          <div className="fb-field"><Icon name="layers" /><select value={sector} onChange={e => setSector(e.target.value)}><option value="">All sectors</option>{D.sectors.map(s => <option key={s.name}>{s.name}</option>)}</select></div>
          <div className="fb-field"><Icon name="building" /><select value={work} onChange={e => setWork(e.target.value)}><option value="">Any work mode</option><option>Must be onsite</option><option>Part Remote</option><option>Fully Remote</option></select></div>
          <Button variant="primary" icon="search">Search</Button>
        </div>

        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 16, margin: "22px 0", flexWrap: "wrap" }}>
          <div className="applied">
            <span style={{ fontFamily: "var(--font-mono)", fontSize: 13, color: "var(--mist-500)" }}>{list.length} result{list.length !== 1 ? "s" : ""}</span>
            {chips.map(([lbl, fn], i) => <span className="chip-f" key={i}>{lbl} <button onClick={fn}>✕</button></span>)}
            {chips.length > 0 && <button className="btn-text" onClick={clearAll} style={{ fontSize: 13 }}>Clear all</button>}
          </div>
          <div className="fb-field" style={{ flex: "0 0 auto" }}>
            <Icon name="arrow-down-up" />
            <select value={sort} onChange={e => setSort(e.target.value)} style={{ background: "#fff", border: "1.5px solid var(--border-strong)", paddingRight: 32 }}>
              <option value="recent">Most recent</option><option value="salary">Highest salary</option><option value="az">A–Z</option>
            </select>
          </div>
        </div>

        {list.length === 0 ? (
          <div className="empty-state" style={{ marginBottom: 80 }}>
            <div className="ei"><Icon name="search-x" /></div>
            <h3 className="h3">No jobs match those filters</h3>
            <p className="lead" style={{ margin: "8px auto 22px", maxWidth: "44ch" }}>Try widening your search, or register your CV and we'll tell you the moment something right comes in.</p>
            <div style={{ display: "flex", gap: 12, justifyContent: "center" }}>
              <Button variant="ghost" onClick={clearAll}>Clear all filters</Button>
              <Button variant="primary" onClick={() => go("candidate")}>Register your CV</Button>
            </div>
          </div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 22, paddingBottom: 40 }}>
            {list.map(j => <JobCard key={j.id} job={j} go={go} />)}
          </div>
        )}

        {list.length > 0 && (
          <div style={{ display: "flex", justifyContent: "center", gap: 8, padding: "12px 0 80px" }}>
            <button className="cbtn" aria-label="Previous"><Icon name="arrow-left" /></button>
            {[1, 2].map(n => <button key={n} className="cbtn" style={n === 1 ? { background: "var(--slate-900)", color: "#fff", borderColor: "var(--slate-900)", fontFamily: "var(--font-display)", fontWeight: 800 } : { fontFamily: "var(--font-display)", fontWeight: 800 }}>{n}</button>)}
            <button className="cbtn" aria-label="Next"><Icon name="arrow-right" /></button>
          </div>
        )}
      </div>
    </main>
  );
}

window.Jobs = Jobs;
