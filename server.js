const express = require("express");
const cors = require("cors");
const db = require("./db");

const app = express();

app.use(cors());
app.use(express.json());

app.get("/", (req, res) => {
  res.send("Backend is working 🚀");
});

// TEST JOBS TABLE (you already have it)
app.get("/jobs", async (req, res) => {
  try {
    const [rows] = await db.query("SELECT * FROM jobs");
    res.json(rows);
  } catch (err) {
    console.log(err);
    res.status(500).send("Database error");
  }
});

app.listen(3000, () => {
  console.log("Server running on http://localhost:3000");
});