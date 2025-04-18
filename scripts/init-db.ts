import { db, pool } from "../server/db";
import {
  users, branches, employees, 
  userRoleEnum, branchEnum, genderEnum, supervisorTypeEnum
} from "../shared/schema";
import { eq } from "drizzle-orm";
import { scrypt, randomBytes } from "crypto";
import { promisify } from "util";

const scryptAsync = promisify(scrypt);

async function hashPassword(password: string) {
  const salt = randomBytes(16).toString("hex");
  const buf = (await scryptAsync(password, salt, 64)) as Buffer;
  return `${buf.toString("hex")}.${salt}`;
}

async function initializeDatabase() {
  try {
    console.log("Checking if database needs initialization...");
    
    // Check if admin user exists
    const adminUser = await db.select().from(users).where(eq(users.username, "admin"));
    
    if (adminUser.length > 0) {
      console.log("Database already initialized - found admin user");
      return;
    }
    
    console.log("Initializing database with default data...");
    
    // Create users with hashed passwords
    console.log("Creating default users...");
    const adminPassword = await hashPassword("admin123");
    const kmgrPassword = await hashPassword("kmgr123");
    const rmgrPassword = await hashPassword("rmgr123");
    const nmgrPassword = await hashPassword("nmgr123");
    
    // Insert admin user
    await db.insert(users).values({
      username: "admin",
      password: adminPassword,
      role: "admin",
      fullName: "System Administrator",
      branchAccess: null
    });
    
    // Create branches
    console.log("Creating default branches...");
    const [kampala] = await db.insert(branches).values({
      name: "Kampala Headquarters",
      code: "kampala",
      location: "Kampala"
    }).returning();
    
    const [rwizi] = await db.insert(branches).values({
      name: "Rwizi Band",
      code: "rwizi",
      location: "Mbarara"
    }).returning();
    
    const [nkyoga] = await db.insert(branches).values({
      name: "North Kyoga Band",
      code: "nkyoga",
      location: "Lira"
    }).returning();
    
    // Insert branch manager users
    await db.insert(users).values([
      {
        username: "kmgr",
        password: kmgrPassword,
        role: "branch_manager",
        fullName: "Kampala Manager",
        branchAccess: "kampala"
      },
      {
        username: "rmgr",
        password: rmgrPassword,
        role: "branch_manager",
        fullName: "Rwizi Manager",
        branchAccess: "rwizi"
      },
      {
        username: "nmgr",
        password: nmgrPassword,
        role: "branch_manager",
        fullName: "N.Kyoga Manager",
        branchAccess: "nkyoga"
      }
    ]);
    
    // Create employees
    console.log("Creating sample employees...");
    await db.insert(employees).values([
      // Kampala employees
      {
        fileNumber: "69488",
        fullName: "John Doe",
        gender: "male",
        rank: "Assistant Superintendent of Police",
        instrument: "Trumpet",
        role: "Band Manager",
        supervisorType: "officer",
        dateJoined: new Date("2015-05-10"),
        phone: "+256712345678",
        email: "john.doe@upf.gov.ug",
        branchId: kampala.id,
        supervisorId: null
      },
      {
        fileNumber: "71256",
        fullName: "Jane Smith",
        gender: "female",
        rank: "Inspector of Police",
        instrument: "Clarinet",
        role: "Section Leader",
        supervisorType: "officer",
        dateJoined: new Date("2016-03-21"),
        phone: "+256723456789",
        email: "jane.smith@upf.gov.ug",
        branchId: kampala.id,
        supervisorId: null
      },
      {
        fileNumber: "74553",
        fullName: "Robert Mukasa",
        gender: "male",
        rank: "Sergeant",
        instrument: "Trombone",
        role: "Musician",
        supervisorType: "nco",
        dateJoined: new Date("2017-09-15"),
        phone: "+256734567890",
        email: "robert.mukasa@upf.gov.ug",
        branchId: kampala.id,
        supervisorId: null
      },
      {
        fileNumber: "79875",
        fullName: "Sarah Nakato",
        gender: "female",
        rank: "Corporal",
        instrument: "Flute",
        role: "Musician",
        supervisorType: "nco",
        dateJoined: new Date("2018-11-03"),
        phone: "+256745678901",
        email: "sarah.nakato@upf.gov.ug",
        branchId: kampala.id,
        supervisorId: null
      },
      {
        fileNumber: "82334",
        fullName: "Mark Ochieng",
        gender: "male",
        rank: "Police Constable",
        instrument: "Percussion",
        role: "Musician",
        supervisorType: "constable",
        dateJoined: new Date("2019-07-19"),
        phone: "+256756789012",
        email: "mark.ochieng@upf.gov.ug",
        branchId: kampala.id,
        supervisorId: null
      },
      
      // Rwizi employees
      {
        fileNumber: "68122",
        fullName: "David Mugisha",
        gender: "male",
        rank: "Inspector of Police",
        instrument: "Saxophone",
        role: "Band Manager",
        supervisorType: "officer",
        dateJoined: new Date("2014-11-22"),
        phone: "+256767890123",
        email: "david.mugisha@upf.gov.ug",
        branchId: rwizi.id,
        supervisorId: null
      },
      {
        fileNumber: "73411",
        fullName: "Patricia Akello",
        gender: "female",
        rank: "Sergeant",
        instrument: "Clarinet",
        role: "Section Leader",
        supervisorType: "nco",
        dateJoined: new Date("2017-05-08"),
        phone: "+256778901234",
        email: "patricia.akello@upf.gov.ug",
        branchId: rwizi.id,
        supervisorId: null
      },
      {
        fileNumber: "77665",
        fullName: "James Byamukama",
        gender: "male",
        rank: "Corporal",
        instrument: "Tuba",
        role: "Musician",
        supervisorType: "nco",
        dateJoined: new Date("2018-06-17"),
        phone: "+256789012345",
        email: "james.byamukama@upf.gov.ug",
        branchId: rwizi.id,
        supervisorId: null
      },
      
      // N.Kyoga employees
      {
        fileNumber: "70254",
        fullName: "Diana Adong",
        gender: "female",
        rank: "Assistant Inspector of Police",
        instrument: "Trumpet",
        role: "Band Manager",
        supervisorType: "officer",
        dateJoined: new Date("2016-01-10"),
        phone: "+256790123456",
        email: "diana.adong@upf.gov.ug",
        branchId: nkyoga.id,
        supervisorId: null
      },
      {
        fileNumber: "75688",
        fullName: "Peter Okello",
        gender: "male",
        rank: "Sergeant",
        instrument: "Trombone",
        role: "Section Leader",
        supervisorType: "nco",
        dateJoined: new Date("2017-12-04"),
        phone: "+256701234567",
        email: "peter.okello@upf.gov.ug",
        branchId: nkyoga.id,
        supervisorId: null
      },
      {
        fileNumber: "81423",
        fullName: "Grace Lamwaka",
        gender: "female",
        rank: "Police Constable",
        instrument: "Percussion",
        role: "Musician",
        supervisorType: "constable",
        dateJoined: new Date("2019-04-22"),
        phone: "+256712345678",
        email: "grace.lamwaka@upf.gov.ug",
        branchId: nkyoga.id,
        supervisorId: null
      }
    ]);
    
    console.log("Database initialization completed successfully!");
  } catch (error) {
    console.error("Error initializing database:", error);
  } finally {
    // Close the database connection
    await pool.end();
  }
}

// Run the initialization
initializeDatabase();