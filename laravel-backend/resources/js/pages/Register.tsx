import { useEffect, useState, useRef } from "react";
import { AcademicRanksOptions } from "../constant/UserDropOptionsData";
import useAddUsers from "../hooks/useAddUsers";
import api from "../components/api/axios";
import { useForm } from "react-hook-form";
import { User } from "../components/shared/types/types";
import { Link } from "react-router-dom";

const profileIcon = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iI2NjYyIgZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00czEuNzktNCA0LTRzLTQuNzktNC00LTQgNCAxLjc5IDQgNHMtMS43OSA0LTQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyYTEgMSAwIDAgMCAxIDFoMTRhMSAxIDAgMCAwIDEtMXYtMmMwLTIuNjYtNS4zMy00LTgtNHoiLz48L3N2Zz4=";

type CollegeType = { id: number; college: string };
type UnitType = {
  id: number;
  unit: string;
}[];

export type RegisterFormType = User & { password: string };

export default function Register() {
  const [colleges, setColleges] = useState<CollegeType[] | null>(null);
  const [units, setUnits] = useState<UnitType | null>(null);
  const [profilePic, setProfilePic] = useState<string | undefined>(undefined);

  const imgInput = useRef<HTMLInputElement | null>(null);

  // Fetch colleges from public endpoint (no auth required)
  useEffect(() => {
    const getColleges = async () => {
      try {
        const response = await api.get("/api/public/colleges");
        setColleges(response.data.data || response.data || []);
      } catch (error) {
        console.error("Error fetching colleges", error);
      }
    };
    getColleges();
  }, []);


  const {
    register,
    setError,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<RegisterFormType>({
    defaultValues: {
      fname: "", lname: "", mi: "", college: "", unit: "",
      academic_rank: "", suffix: "", email: "", password: "", image_path: "",
    },
  });

  const { onSubmit, loading } = useAddUsers(
    "/api/register",
    "/login",
    setError,
  );

  const college = watch("college");

  useEffect(() => {
    if (college) {
      const getUnits = async () => {
        try {
          const response = await api.get("/api/public/units", {
            params: { college: college },
          });

          const fetchedUnits = response.data.data || response.data || [];
          setUnits(fetchedUnits);
        } catch (error) {
          console.error("Error fetching units", error);
        }
      };
      getUnits();
    } else {
      setUnits(null);
    }
  }, [college]);

  const uploadProfile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const { files } = e.target;
    if (!files || !files[0]) return;

    setProfilePic(URL.createObjectURL(files[0]));

    const formImage = new FormData();
    formImage.append("image_path", files[0]);
    try {
      const res = await api.post("/api/public/file-upload-public", formImage, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      const imagePath = res.data.data?.image_path || res.data?.image_path;
      setValue("image_path", imagePath);
    } catch (error) {
      console.error("File upload failed:", error);
      alert("Failed to upload image. Please try again.");
    }
  };

  return (
    <div className="flex min-h-[calc(100svh-4.5rem)] items-center justify-center py-10 bg-gray-50">
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="w-full max-w-[50rem] rounded-md bg-white p-10 shadow-lg"
      >
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-bold">Faculty Registration</h1>
          <p className="mt-2 text-gray-500">Create your account to access the portal</p>
        </div>

        <div className="mb-8 flex justify-center">
          <div className="relative h-24 w-24 cursor-pointer">
            <div
              onClick={() => imgInput.current?.click()}
              className="relative h-full w-full overflow-hidden rounded-full border-2 border-blue-500 bg-white"
            >
              <img
                src={profilePic || profileIcon}
                alt="Profile"
                className="absolute left-1/2 top-1/2 h-full w-full -translate-x-1/2 -translate-y-1/2 object-cover"
              />
              <input
                type="file"
                accept="image/*"
                ref={imgInput}
                onChange={uploadProfile}
                className="hidden"
              />
            </div>
            <div className="mt-2 text-center text-xs font-semibold text-gray-500">Upload Photo</div>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">First Name</label>
            <input
              {...register("fname", { required: "First name is required." })}
              className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.fname && <p className="mt-1 text-xs text-red-600">{errors.fname.message}</p>}
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Last Name</label>
            <input
              {...register("lname", { required: "Last name is required." })}
              className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.lname && <p className="mt-1 text-xs text-red-600">{errors.lname.message}</p>}
          </div>

          <div className="flex space-x-4">
            <div className="w-1/2">
              <label className="mb-1 block text-sm font-bold text-gray-700">M.I.</label>
              <input
                {...register("mi", { maxLength: 1 })}
                className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div className="w-1/2">
              <label className="mb-1 block text-sm font-bold text-gray-700">Suffix</label>
              <input
                {...register("suffix")}
                placeholder="e.g. Jr, Sr"
                className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Department</label>
            <select
              {...register("college", { required: "College is required." })}
              className="w-full rounded-md border bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="" disabled>Select College Department</option>
              {colleges?.map((col: any) => (
                <option key={col.id} value={col.college}>{col.college}</option>
              ))}
            </select>
            {errors.college && <p className="mt-1 text-xs text-red-600">{errors.college.message}</p>}
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Unit</label>
            <select
              {...register("unit", { required: "Unit is required." })}
              disabled={!college || units?.length === 0}
              className="w-full rounded-md border bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
            >
              <option value="" disabled>Select a unit</option>
              {units?.map((u: any) => (
                <option key={u.id} value={u.unit}>{u.unit}</option>
              ))}
            </select>
            {errors.unit && <p className="mt-1 text-xs text-red-600">{errors.unit.message}</p>}
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Academic Rank</label>
            <select
              {...register("academic_rank", { required: "Rank is required." })}
              className="w-full rounded-md border bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="" disabled>Select an academic rank</option>
              {AcademicRanksOptions.map((rank: string) => (
                <option key={rank} value={rank}>{rank}</option>
              ))}
            </select>
            {errors.academic_rank && <p className="mt-1 text-xs text-red-600">{errors.academic_rank.message}</p>}
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Email</label>
            <input
              type="email"
              {...register("email", { required: "Email is required.", pattern: /^\S+@\S+$/i })}
              className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email.message}</p>}
          </div>

          <div>
            <label className="mb-1 block text-sm font-bold text-gray-700">Password</label>
            <input
              type="password"
              {...register("password", { required: "Password is required.", minLength: { value: 8, message: "Min 8 characters." } })}
              className="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>}
          </div>
        </div>

        <div className="mt-10">
          <button
            type="submit"
            disabled={loading}
            className={`w-full rounded-md px-4 py-2 font-semibold text-white transition-colors ${loading ? "cursor-not-allowed bg-blue-400" : "bg-blue-700 hover:bg-blue-600 shadow-md"
              }`}
          >
            {loading ? "Registering..." : "Register"}
          </button>

          <div className="mt-6 text-center text-sm text-gray-600">
            Already have an account?{" "}
            <Link to="/login" className="font-bold text-blue-700 hover:underline">
              Log in here
            </Link>
          </div>
        </div>
      </form>
    </div>
  );
}
