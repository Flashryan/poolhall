/* Poolhall UI kit — Home (candidate-led) */

function Home({ go, path }) {
  const D = window.PH_DATA;
  const featured = D.jobs.filter(j => j.featured);
  return (
    <main>
      {/* hero */}
      <section className="hero">
        <div className="hero-media"><img src={window.HERO_IMG} alt="" onError={e => e.target.style.display='none'} /></div>
        <div className="container">
          <div className="hero-inner">
            <p className="eyebrow on-dark">Independent recruitment · West Midlands</p>
            <h1 className="h-display">Find your next job with us</h1>
            <p className="lead on-dark">We match incredible roles with amazing people. Honest advice, exclusive roles and a process that puts you first.</p>
            <SearchBar go={go} />
            <div className="trust-row">
              <span className="ti"><Icon name="star" /> 5.0 on Google Reviews</span>
              <span className="ti"><Icon name="shield-check" /> Quality & ethical, since 2021</span>
              <span className="ti"><Icon name="briefcase" /> 20+ live roles right now</span>
            </div>
          </div>
        </div>
      </section>

      {/* featured jobs carousel */}
      <section className="section bg-paper">
        <div className="container">
          <SectionHead eyebrow="Current roles"
            title="Featured jobs this week"
            lead="Hand-picked roles from our latest live vacancies. Swipe to see more."
            action={<Button variant="ghost" iconRight="arrow-right" onClick={() => go("jobs")}>View all jobs</Button>} />
          <Carousel>
            {featured.concat(D.jobs.filter(j => !j.featured)).map(j => (
              <JobCard key={j.id} job={j} go={go} />
            ))}
          </Carousel>
        </div>
      </section>

      {/* sectors */}
      <section className="section bg-white">
        <div className="container">
          <SectionHead eyebrow="Sectors we cover"
            title="Specialists in the work that builds Britain"
            lead="Decades of combined experience across the industries we know best." />
          <div className="sector-grid">
            {D.sectors.map(s => <SectorTile key={s.name} s={s} go={go} />)}
          </div>
        </div>
      </section>

      {/* how we work — candidate steps */}
      <section className="section bg-paper">
        <div className="container">
          <SectionHead eyebrow="How we work with you" title="Three steps to your next role" />
          <div className="steps">
            <div className="step"><span className="num">1</span><h4>Tell us what you want</h4><p>Share your CV and what a great next move looks like. No pressure, no spam, just a real conversation.</p></div>
            <div className="step"><span className="num">2</span><h4>We match you to roles</h4><p>We only put you forward for roles that genuinely fit your skills, salary and ambitions.</p></div>
            <div className="step"><span className="num">3</span><h4>We guide you to offer</h4><p>Interview prep, honest feedback and support right through to your first day and beyond.</p></div>
          </div>
        </div>
      </section>

      {/* stats strip */}
      <section className="section tight bg-navy">
        <div className="container"><StatStrip /></div>
      </section>

      {/* google reviews */}
      <section className="section bg-white">
        <div className="container">
          <SectionHead eyebrow="Google reviews"
            title="Rated five stars by the people we place"
            lead="Real feedback from candidates and clients across the UK."
            action={<a href="#" onClick={e=>e.preventDefault()} className="jc-link">Read all reviews <Icon name="arrow-right" /></a>} />
          <Carousel>
            {D.reviews.map((r, i) => <ReviewCard key={i} r={r} />)}
          </Carousel>
        </div>
      </section>

      {/* employer CTA */}
      <section className="section bg-mist">
        <div className="container">
          <div className="split">
            <div>
              <p className="eyebrow">Looking to hire?</p>
              <h2 className="h2">We'll represent your business like it's our own</h2>
              <p className="lead" style={{ marginTop: 14 }}>We work with PLCs and SMEs on exclusive and exciting roles. Tell us who you need and we'll find the right people.</p>
              <div style={{ display: "flex", gap: 12, marginTop: 24 }}>
                <Button variant="navy" iconRight="arrow-right" onClick={() => go("employers")}>For employers</Button>
                <Button variant="ghost" icon="phone">0121 516 3000</Button>
              </div>
            </div>
            <div className="media-frame">
              <img src={window.SPLIT_IMG} alt="" onError={e => e.target.parentElement.style.background='var(--navy-700)'} />
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

window.Home = Home;
